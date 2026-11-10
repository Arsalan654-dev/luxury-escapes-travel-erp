<?php
// =====================================================
// POST /api/clients/delete.php
// Body: { id }
// Blocks deletion if the client has any invoices/vouchers/ledger
// history — deactivates instead so records stay intact.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_clients');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Client id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Client not found']);
    exit;
}

// Check if this client has any transactional history
$checks = [
    'hotel_invoices' => "SELECT COUNT(*) c FROM hotel_invoices WHERE client_id = ?",
    'transport_vouchers' => "SELECT COUNT(*) c FROM transport_vouchers WHERE client_id = ?",
    'visa_invoices' => "SELECT COUNT(*) c FROM visa_invoices WHERE client_id = ?",
    'ledger_entries' => "SELECT COUNT(*) c FROM ledger_entries WHERE client_id = ?",
];
$hasHistory = false;
foreach ($checks as $table => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    if ($stmt->fetch()['c'] > 0) {
        $hasHistory = true;
        break;
    }
}

if ($hasHistory) {
    // Don't destroy financial history — deactivate instead
    $stmt = $pdo->prepare("UPDATE clients SET is_active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode([
        'status' => 'success',
        'message' => 'This client has booking/ledger history, so it was deactivated instead of deleted (to preserve financial records).',
        'action' => 'deactivated',
    ]);
} else {
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['status' => 'success', 'message' => 'Client deleted', 'action' => 'deleted']);
}
