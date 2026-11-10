<?php
// =====================================================
// POST /api/users/update.php  (admin only)
// Body: { id, role, is_active }
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
requirePermission($user, 'manage_users');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'User id is required']);
    exit;
}

if ((int)$id === (int)$user['id'] && isset($input['is_active']) && !$input['is_active']) {
    http_response_code(400);
    echo json_encode(['error' => 'You cannot deactivate your own account']);
    exit;
}

$permissionsJson = null;
if (isset($input['permissions']) && is_array($input['permissions'])) {
    $permissionsJson = json_encode($input['permissions']);
}

$stmt = $pdo->prepare("UPDATE users SET role = ?, permissions = ?, is_active = ? WHERE id = ?");
$stmt->execute([
    $input['role'] ?? 'operations',
    $permissionsJson,
    $input['is_active'] ?? 1,
    $id,
]);

echo json_encode(['status' => 'success', 'message' => 'User updated']);
