<?php
// =====================================================
// POST /api/payments/delete.php  (admin, accountant only)
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
    echo json_encode(['error' => 'Payment id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    echo json_encode(['error' => 'Payment not found']);
    exit;
}

$pdo->beginTransaction();
try {
    // Delete the payment record, then its linked ledger entry, then recalc balance
    $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM ledger_entries WHERE id = ?")->execute([$payment['ledger_entry_id']]);
    recalcClientLedger($pdo, $payment['client_id']);
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Payment deleted and Shajrah balance recalculated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete payment', 'details' => $e->getMessage()]);
}
