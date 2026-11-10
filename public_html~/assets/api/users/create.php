<?php
// =====================================================
// POST /api/users/create.php  (admin only)
// Body: { name, email, password, role }
// role: admin | accountant | operations | viewer
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

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$role = $input['role'] ?? 'operations';

if (!$name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Name, email and password are required']);
    exit;
}

if (!in_array($role, ['admin', 'accountant', 'operations', 'viewer'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'A user with this email already exists']);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)");
$stmt->execute([$name, $email, $hash, $role]);

echo json_encode(['status' => 'success', 'message' => 'User created', 'id' => $pdo->lastInsertId()]);
