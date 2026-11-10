<?php
// =====================================================
// GET /api/users/list.php  (admin only)
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_users');

$stmt = $pdo->query("SELECT id, name, email, role, permissions, is_active, created_at FROM users ORDER BY created_at DESC");
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
