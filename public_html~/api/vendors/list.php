<?php
// =====================================================
// GET /api/vendors/list.php?search=&is_active=1
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$isActive = $_GET['is_active'] ?? null;

$sql = "SELECT v.*, c.id as client_account_id, c.client_code
        FROM vendors v
        LEFT JOIN clients c ON c.id = v.linked_client_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (v.name LIKE ? OR v.phone LIKE ? OR v.email LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

if ($isActive !== null) {
    $sql .= " AND v.is_active = ?";
    $params[] = $isActive;
}

$sql .= " ORDER BY v.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

echo json_encode(['status' => 'success', 'data' => $vendors]);
