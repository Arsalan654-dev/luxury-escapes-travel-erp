<?php
// =====================================================
// GET /api/dashboard/stats.php
// Returns summary numbers for the dashboard:
// total receivable, total payable, client/vendor counts,
// this month's revenue vs cost, recent activity.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

// Total receivable = sum of positive client balances (they owe us)
// Total we've collected = sum of credits (payments)
$stmt = $pdo->query("
    SELECT
        c.id,
        c.linked_vendor_id,
        COALESCE(SUM(l.debit) - SUM(l.credit), 0) as balance
    FROM clients c
    LEFT JOIN ledger_entries l ON l.client_id = c.id
    WHERE c.is_active = 1
    GROUP BY c.id
");
$balances = $stmt->fetchAll();

$totalReceivable = 0;   // end-customers who owe us
$totalPayableToUs = 0;  // vendor-accounts that owe us (they bought from us)
foreach ($balances as $b) {
    if ($b['balance'] > 0) {
        if ($b['linked_vendor_id']) {
            $totalPayableToUs += $b['balance'];
        } else {
            $totalReceivable += $b['balance'];
        }
    }
}

// Counts
$clientCount = $pdo->query("SELECT COUNT(*) c FROM clients WHERE is_active = 1 AND linked_vendor_id IS NULL")->fetch()['c'];
$vendorCount = $pdo->query("SELECT COUNT(*) c FROM vendors WHERE is_active = 1")->fetch()['c'];
$hotelInvoiceCount = $pdo->query("SELECT COUNT(*) c FROM hotel_invoices")->fetch()['c'];

// This month: purchase total (cost) vs sale total (revenue from vendor-clients)
$month = date('Y-m');
$stmt = $pdo->prepare("
    SELECT transaction_type, COALESCE(SUM(total_amount),0) as total
    FROM hotel_invoices
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    GROUP BY transaction_type
");
$stmt->execute([$month]);
$monthTotals = ['purchase' => 0, 'sale' => 0];
foreach ($stmt->fetchAll() as $row) {
    $monthTotals[$row['transaction_type']] = (float)$row['total'];
}

// Recent activity (last 8 hotel invoices)
$stmt = $pdo->query("
    SELECT hi.id, hi.booking_ref, hi.hotel_name, hi.total_amount, hi.currency_code,
           hi.transaction_type, hi.created_at, c.name as client_name
    FROM hotel_invoices hi
    JOIN clients c ON c.id = hi.client_id
    ORDER BY hi.created_at DESC
    LIMIT 8
");
$recent = $stmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'total_receivable' => $totalReceivable,
    'total_payable_to_us' => $totalPayableToUs,
    'client_count' => (int)$clientCount,
    'vendor_count' => (int)$vendorCount,
    'hotel_invoice_count' => (int)$hotelInvoiceCount,
    'month_purchase_total' => $monthTotals['purchase'],
    'month_sale_total' => $monthTotals['sale'],
    'recent_activity' => $recent,
]);
