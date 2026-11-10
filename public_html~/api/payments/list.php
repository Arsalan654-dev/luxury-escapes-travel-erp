<?php
// =====================================================
// GET /api/payments/list.php?client_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$clientId = $_GET['client_id'] ?? null;

$sql = "SELECT p.*, c.name as client_name, c.client_code
        FROM payments p
        JOIN clients c ON c.id = p.client_id
        WHERE 1=1";
$params = [];

if ($clientId) {
    $sql .= " AND p.client_id = ?";
    $params[] = $clientId;
}

$sql .= " ORDER BY p.payment_date DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
