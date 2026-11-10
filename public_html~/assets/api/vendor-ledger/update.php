<?php
// =====================================================
// POST /api/vendor-ledger/update.php  (admin, accountant only)
// Body: { id, entry_date, narration, debit, credit }
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
requirePermission($user, 'manage_ledger');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Ledger entry id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM vendor_ledger_entries WHERE id = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();

if (!$old) {
    http_response_code(404);
    echo json_encode(['error' => 'Ledger entry not found']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE vendor_ledger_entries
    SET entry_date = ?, narration = ?, debit = ?, credit = ?, dispute_status = ?, dispute_note = ?, is_edited = 1, updated_by = ?
    WHERE id = ?
");
$stmt->execute([
    $input['entry_date'] ?? $old['entry_date'],
    $input['narration'] ?? $old['narration'],
    $input['debit'] ?? $old['debit'],
    $input['credit'] ?? $old['credit'],
    $input['dispute_status'] ?? $old['dispute_status'],
    $input['dispute_note'] ?? $old['dispute_note'],
    $user['id'],
    $id,
]);

recalcVendorLedger($pdo, $old['vendor_id']);

echo json_encode(['status' => 'success', 'message' => 'Ledger entry updated']);
