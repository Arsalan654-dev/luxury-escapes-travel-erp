<?php
// =====================================================
// POST /api/visa/delete.php  (admin, accountant only)
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/voucher_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Visa invoice id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM visa_invoices WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) {
    http_response_code(404);
    echo json_encode(['error' => 'Visa invoice not found']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM visa_client_vouchers WHERE visa_invoice_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM package_booking_travelers WHERE visa_invoice_id = ?")->execute([$id]);

    $clientLedgerCount = $pdo->prepare("SELECT COUNT(*) as count FROM ledger_entries WHERE linked_visa_invoice_id = ?");
    $clientLedgerCount->execute([$id]);
    $clientLedgerRow = $clientLedgerCount->fetch();
    if ($clientLedgerRow && $clientLedgerRow['count'] > 0) {
        $pdo->prepare("DELETE FROM ledger_entries WHERE linked_visa_invoice_id = ?")->execute([$id]);
        recalcClientLedger($pdo, $v['client_id']);
    }

    $vendorLedgerCount = $pdo->prepare("SELECT COUNT(*) as count FROM vendor_ledger_entries WHERE linked_visa_invoice_id = ?");
    $vendorLedgerCount->execute([$id]);
    $vendorLedgerRow = $vendorLedgerCount->fetch();
    if ($vendorLedgerRow && $vendorLedgerRow['count'] > 0) {
        $pdo->prepare("DELETE FROM vendor_ledger_entries WHERE linked_visa_invoice_id = ?")->execute([$id]);
        if ($v['vendor_id']) recalcVendorLedger($pdo, $v['vendor_id']);
    }

    $pdo->prepare("DELETE FROM visa_invoices WHERE id = ?")->execute([$id]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Visa invoice and related records deleted']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete', 'details' => $e->getMessage()]);
}
