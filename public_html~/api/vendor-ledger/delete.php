<?php
// =====================================================
// POST /api/vendor-ledger/delete.php  (admin, accountant only)
// Body: { id }
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
$entry = $stmt->fetch();

if (!$entry) {
    http_response_code(404);
    echo json_encode(['error' => 'Ledger entry not found']);
    exit;
}

$pdo->prepare("DELETE FROM vendor_payments WHERE ledger_entry_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM vendor_ledger_entries WHERE id = ?")->execute([$id]);

recalcVendorLedger($pdo, $entry['vendor_id']);

echo json_encode(['status' => 'success', 'message' => 'Ledger entry deleted']);
