<?php
// =====================================================
// GET /api/auth/me.php
// Header: Authorization: Bearer <token>
// Returns the currently logged-in user's info
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

echo json_encode(['status' => 'success', 'user' => $user]);
