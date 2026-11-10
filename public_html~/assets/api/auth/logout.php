<?php
// =====================================================
// POST /api/auth/logout.php
// Header: Authorization: Bearer <token>
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
    $token = trim(substr($authHeader, 7));
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
    $stmt->execute([$token]);
}

echo json_encode(['status' => 'success', 'message' => 'Logged out']);
