<?php
// =====================================================
// POST /api/transport/update.php
// Body: { id, ...same fields as create.php }
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
    echo json_encode(['error' => 'Transport id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM transport_vouchers WHERE id = ?");
$stmt->execute([$id]);
$existing = $stmt->fetch();
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Transport booking not found']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        UPDATE transport_vouchers SET
            vendor_id = ?, transaction_type = ?, route = ?, vehicle_type = ?,
            pickup_date = ?, pickup_time = ?, flight_details = ?, contact_person = ?,
            total_pax = ?, amount = ?, currency_code = ?, booking_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['vendor_id'] ?: null,
        $input['transaction_type'] ?? $existing['transaction_type'],
        $input['route'],
        $input['vehicle_type'] ?? null,
        $input['pickup_date'],
        $input['pickup_time'] ?? null,
        $input['flight_details'] ?? null,
        $input['contact_person'] ?? null,
        $input['total_pax'] ?? 1,
        $input['amount'],
        $input['currency_code'] ?? 'SAR',
        $input['booking_notes'] ?? null,
        $id,
    ]);

    $stmt = $pdo->prepare("SELECT id, client_id, voucher_no FROM ledger_entries WHERE linked_transport_voucher_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $ledgerRow = $stmt->fetch();

    if ($ledgerRow) {
        $narration = $input['route'] . ' Transport - ' . ($input['vehicle_type'] ?? '') . ' - ' . date('d M Y', strtotime($input['pickup_date']));
        $upd = $pdo->prepare("UPDATE ledger_entries SET entry_date = ?, narration = ?, debit = ?, is_edited = 1, updated_by = ? WHERE id = ?");
        $upd->execute([$input['pickup_date'], $narration, $input['amount'], $user['id'], $ledgerRow['id']]);
        recalcClientLedger($pdo, $ledgerRow['client_id']);
    }

    // Sync vendor payable ledger
    $narration = $input['route'] . ' Transport - ' . ($input['vehicle_type'] ?? '') . ' - ' . date('d M Y', strtotime($input['pickup_date']));
    $stmt = $pdo->prepare("SELECT id, vendor_id FROM vendor_ledger_entries WHERE linked_transport_voucher_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $vendorLedgerRow = $stmt->fetch();
    $newTransactionType = $input['transaction_type'] ?? $existing['transaction_type'];

    if ($vendorLedgerRow) {
        if (!empty($input['vendor_id']) && $newTransactionType === 'purchase') {
            $upd = $pdo->prepare("UPDATE vendor_ledger_entries SET entry_date = ?, narration = ?, credit = ?, is_edited = 1, updated_by = ? WHERE id = ?");
            $upd->execute([$input['pickup_date'], $narration, $input['amount'], $user['id'], $vendorLedgerRow['id']]);
            recalcVendorLedger($pdo, $input['vendor_id']);
        } else {
            $pdo->prepare("DELETE FROM vendor_ledger_entries WHERE id = ?")->execute([$vendorLedgerRow['id']]);
            recalcVendorLedger($pdo, $vendorLedgerRow['vendor_id']);
        }
    } elseif (!empty($input['vendor_id']) && $newTransactionType === 'purchase') {
        addVendorLedgerEntry($pdo, [
            'vendor_id' => $input['vendor_id'],
            'entry_date' => $input['pickup_date'],
            'voucher_type' => 'TN',
            'voucher_no' => $ledgerRow['voucher_no'] ?? ('TN-' . $id),
            'narration' => $narration,
            'linked_transport_voucher_id' => $id,
            'credit' => $input['amount'],
            'debit' => 0,
            'currency_code' => $input['currency_code'] ?? 'SAR',
            'created_by' => $user['id'],
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Transport booking updated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update transport booking', 'details' => $e->getMessage()]);
}
