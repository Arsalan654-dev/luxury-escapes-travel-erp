<?php
// =====================================================
// POST /api/clients/update.php
// Body: { id, name, phone, email, cnic_passport, address,
//         default_currency, is_active, linked_vendor_id }
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

$stmt = $pdo->prepare("
    UPDATE clients SET
        name = ?, phone = ?, email = ?, cnic_passport = ?, address = ?,
        default_currency = ?, is_active = ?, linked_vendor_id = ?
    WHERE id = ?
");
$stmt->execute([
    $input['name'] ?? '',
    $input['phone'] ?? null,
    $input['email'] ?? null,
    $input['cnic_passport'] ?? null,
    $input['address'] ?? null,
    $input['default_currency'] ?? 'SAR',
    $input['is_active'] ?? 1,
    $input['linked_vendor_id'] ?? null,
    $id,
]);

echo json_encode(['status' => 'success', 'message' => 'Client updated']);
