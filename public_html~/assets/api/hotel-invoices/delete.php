<?php
// =====================================================
// POST /api/hotel-invoices/delete.php  (admin, accountant only)
// Body: { id }
// Also removes the linked client voucher, ledger entry, and
// vendor ledger entry (if any), then recalculates balances.
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
    echo json_encode(['error' => 'Invoice id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM hotel_invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    http_response_code(404);
    echo json_encode(['error' => 'Hotel invoice not found']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM hotel_vouchers WHERE hotel_invoice_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM package_booking_travelers WHERE package_booking_id IN (SELECT id FROM package_bookings WHERE hotel_invoice_id = ?)")->execute([$id]);
    $pdo->prepare("DELETE FROM package_bookings WHERE hotel_invoice_id = ?")->execute([$id]);

    $clientLedgerCount = $pdo->prepare("SELECT COUNT(*) as count FROM ledger_entries WHERE linked_hotel_invoice_id = ?");
    $clientLedgerCount->execute([$id]);
    $clientLedgerRow = $clientLedgerCount->fetch();
    if ($clientLedgerRow && $clientLedgerRow['count'] > 0) {
        $pdo->prepare("DELETE FROM ledger_entries WHERE linked_hotel_invoice_id = ?")->execute([$id]);
        recalcClientLedger($pdo, $invoice['client_id']);
    }

    $vendorLedgerCount = $pdo->prepare("SELECT COUNT(*) as count FROM vendor_ledger_entries WHERE linked_hotel_invoice_id = ?");
    $vendorLedgerCount->execute([$id]);
    $vendorLedgerRow = $vendorLedgerCount->fetch();
    if ($vendorLedgerRow && $vendorLedgerRow['count'] > 0) {
        $pdo->prepare("DELETE FROM vendor_ledger_entries WHERE linked_hotel_invoice_id = ?")->execute([$id]);
        if ($invoice['vendor_id']) recalcVendorLedger($pdo, $invoice['vendor_id']);
    }

    $pdo->prepare("DELETE FROM hotel_invoices WHERE id = ?")->execute([$id]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Hotel invoice and related records deleted']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete', 'details' => $e->getMessage()]);
}
