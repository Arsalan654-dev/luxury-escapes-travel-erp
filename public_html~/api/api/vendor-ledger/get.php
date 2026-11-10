<?php
// =====================================================
// GET /api/vendor-ledger/get.php?vendor_id=123
// Returns vendor info + full payable ledger + running balance.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$vendorId = $_GET['vendor_id'] ?? null;
if (!$vendorId) {
    http_response_code(400);
    echo json_encode(['error' => 'vendor_id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    http_response_code(404);
    echo json_encode(['error' => 'Vendor not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(credit) - SUM(debit), 0) as balance FROM vendor_ledger_entries WHERE vendor_id = ?");
$stmt->execute([$vendorId]);
$vendor['payable_balance'] = $stmt->fetch()['balance'];

$stmt = $pdo->prepare("SELECT * FROM vendor_ledger_entries WHERE vendor_id = ? ORDER BY entry_date ASC, id ASC");
$stmt->execute([$vendorId]);
$ledger = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM vendor_payments WHERE vendor_id = ? ORDER BY payment_date DESC");
$stmt->execute([$vendorId]);
$payments = $stmt->fetchAll();

echo json_encode(['status' => 'success', 'vendor' => $vendor, 'ledger' => $ledger, 'payments' => $payments]);
