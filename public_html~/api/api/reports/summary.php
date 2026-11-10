<?php
// =====================================================
// GET /api/reports/summary.php?period=daily|weekly|monthly&date=YYYY-MM-DD
// Returns invoice/voucher counts and totals for the given period.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$period = $_GET['period'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');

switch ($period) {
    case 'weekly':
        $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        break;
    case 'monthly':
        $start = date('Y-m-01', strtotime($date));
        $end = date('Y-m-t', strtotime($date));
        break;
    default: // daily
        $start = $date;
        $end = $date;
}

function moduleTotals(PDO $pdo, string $table, string $amountCol, string $start, string $end) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM($amountCol), 0) as total
        FROM $table
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    $row = $stmt->fetch();
    return ['count' => (int)$row['count'], 'total' => (float)$row['total']];
}

function voucherCount(PDO $pdo, string $table, string $start, string $end) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    return (int)$stmt->fetch()['count'];
}

$data = [
    'period' => $period,
    'start' => $start,
    'end' => $end,
    'hotel_invoices' => moduleTotals($pdo, 'hotel_invoices', 'total_amount', $start, $end),
    'transport' => moduleTotals($pdo, 'transport_vouchers', 'amount', $start, $end),
    'visa' => moduleTotals($pdo, 'visa_invoices', 'amount', $start, $end),
    'hotel_vouchers' => voucherCount($pdo, 'hotel_vouchers', $start, $end),
    'transport_vouchers' => voucherCount($pdo, 'transport_client_vouchers', $start, $end),
    'visa_vouchers' => voucherCount($pdo, 'visa_client_vouchers', $start, $end),
    'client_payments' => moduleTotals($pdo, 'payments', 'amount', $start, $end),
    'vendor_payments' => moduleTotals($pdo, 'vendor_payments', 'amount', $start, $end),
];

echo json_encode(['status' => 'success', 'data' => $data]);
