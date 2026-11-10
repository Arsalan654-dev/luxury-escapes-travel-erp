<?php
// Temporary: show real PHP errors instead of a blank 500
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Total Payable = what WE owe vendors (from vendor_ledger_entries)
// Computed per-vendor, only counting vendors we still owe (positive balance) —
// so one over-paid vendor can't drag the total negative/misleading.
$stmt = $pdo->query("
    SELECT vendor_id, COALESCE(SUM(credit) - SUM(debit), 0) as balance
    FROM vendor_ledger_entries
    GROUP BY vendor_id
");
$vendorBalances = $stmt->fetchAll();
$totalVendorPayable = 0;
foreach ($vendorBalances as $vb) {
    if ($vb['balance'] > 0) {
        $totalVendorPayable += $vb['balance'];
    }
}
$clientCount = $pdo->query("SELECT COUNT(*) c FROM clients WHERE is_active = 1 AND linked_vendor_id IS NULL")->fetch()['c'];
$vendorCount = $pdo->query("SELECT COUNT(*) c FROM vendors WHERE is_active = 1")->fetch()['c'];
$hotelInvoiceCount = $pdo->query("SELECT COUNT(*) c FROM hotel_invoices")->fetch()['c'];
$transportCount = $pdo->query("SELECT COUNT(*) c FROM transport_vouchers")->fetch()['c'];
$visaCount = $pdo->query("SELECT COUNT(*) c FROM visa_invoices")->fetch()['c'];

// This month: purchase total (cost) vs sale total (revenue) across
// hotel invoices, transport, and visa
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

$monthTotals = ['purchase' => 0, 'sale' => 0];

$stmt = $pdo->prepare("
    SELECT transaction_type, COALESCE(SUM(total_amount),0) as total
    FROM hotel_invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY transaction_type
");
$stmt->execute([$monthStart, $monthEnd]);
foreach ($stmt->fetchAll() as $row) {
    $monthTotals[$row['transaction_type']] += (float)$row['total'];
}

$stmt = $pdo->prepare("
    SELECT transaction_type, COALESCE(SUM(amount),0) as total
    FROM transport_vouchers
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY transaction_type
");
$stmt->execute([$monthStart, $monthEnd]);
foreach ($stmt->fetchAll() as $row) {
    $monthTotals[$row['transaction_type']] += (float)$row['total'];
}

$stmt = $pdo->prepare("
    SELECT transaction_type, COALESCE(SUM(amount),0) as total
    FROM visa_invoices
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY transaction_type
");
$stmt->execute([$monthStart, $monthEnd]);
foreach ($stmt->fetchAll() as $row) {
    $monthTotals[$row['transaction_type']] += (float)$row['total'];
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
    'total_vendor_payable' => $totalVendorPayable,
    'client_count' => (int)$clientCount,
    'vendor_count' => (int)$vendorCount,
    'hotel_invoice_count' => (int)$hotelInvoiceCount,
    'transport_count' => (int)$transportCount,
    'visa_count' => (int)$visaCount,
    'month_purchase_total' => $monthTotals['purchase'],
    'month_sale_total' => $monthTotals['sale'],
    'recent_activity' => $recent,
]);
