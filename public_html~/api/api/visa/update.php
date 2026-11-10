<?php
// =====================================================
// POST /api/visa/update.php
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
    echo json_encode(['error' => 'Visa invoice id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM visa_invoices WHERE id = ?");
$stmt->execute([$id]);
$existing = $stmt->fetch();
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Visa invoice not found']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        UPDATE visa_invoices SET
            vendor_id = ?, transaction_type = ?, visa_type = ?, country = ?,
            guest_name = ?, issue_date = ?, expiry_date = ?, amount = ?,
            currency_code = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['vendor_id'] ?: null,
        $input['transaction_type'] ?? $existing['transaction_type'],
        $input['visa_type'],
        $input['country'] ?? null,
        $input['guest_name'],
        $input['issue_date'],
        $input['expiry_date'] ?? null,
        $input['amount'],
        $input['currency_code'] ?? 'PKR',
        $input['notes'] ?? null,
        $id,
    ]);

    $stmt = $pdo->prepare("SELECT id, client_id, voucher_no FROM ledger_entries WHERE linked_visa_invoice_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $ledgerRow = $stmt->fetch();

    if ($ledgerRow) {
        $narration = $input['visa_type'] . ' - ' . $input['guest_name'];
        $upd = $pdo->prepare("UPDATE ledger_entries SET entry_date = ?, narration = ?, debit = ?, is_edited = 1, updated_by = ? WHERE id = ?");
        $upd->execute([$input['issue_date'], $narration, $input['amount'], $user['id'], $ledgerRow['id']]);
        recalcClientLedger($pdo, $ledgerRow['client_id']);
    }

    // Sync vendor payable ledger
    $narration = $input['visa_type'] . ' - ' . $input['guest_name'];
    $stmt = $pdo->prepare("SELECT id, vendor_id FROM vendor_ledger_entries WHERE linked_visa_invoice_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $vendorLedgerRow = $stmt->fetch();
    $newTransactionType = $input['transaction_type'] ?? $existing['transaction_type'];

    if ($vendorLedgerRow) {
        if (!empty($input['vendor_id']) && $newTransactionType === 'purchase') {
            $upd = $pdo->prepare("UPDATE vendor_ledger_entries SET entry_date = ?, narration = ?, credit = ?, is_edited = 1, updated_by = ? WHERE id = ?");
            $upd->execute([$input['issue_date'], $narration, $input['amount'], $user['id'], $vendorLedgerRow['id']]);
            recalcVendorLedger($pdo, $input['vendor_id']);
        } else {
            $pdo->prepare("DELETE FROM vendor_ledger_entries WHERE id = ?")->execute([$vendorLedgerRow['id']]);
            recalcVendorLedger($pdo, $vendorLedgerRow['vendor_id']);
        }
    } elseif (!empty($input['vendor_id']) && $newTransactionType === 'purchase') {
        addVendorLedgerEntry($pdo, [
            'vendor_id' => $input['vendor_id'],
            'entry_date' => $input['issue_date'],
            'voucher_type' => 'DN',
            'voucher_no' => $ledgerRow['voucher_no'] ?? ('DN-' . $id),
            'narration' => $narration,
            'linked_visa_invoice_id' => $id,
            'credit' => $input['amount'],
            'debit' => 0,
            'currency_code' => $input['currency_code'] ?? 'PKR',
            'created_by' => $user['id'],
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Visa invoice updated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update visa invoice', 'details' => $e->getMessage()]);
}
