<?php
// =====================================================
// GET /api/visa-vouchers/list.php?search=&client_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$clientId = $_GET['client_id'] ?? null;

$sql = "SELECT vv.*, c.name as client_name, c.client_code
        FROM visa_client_vouchers vv
        JOIN clients c ON c.id = vv.client_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (vv.visa_type LIKE ? OR vv.guest_name LIKE ? OR vv.voucher_ref LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($clientId) {
    $sql .= " AND vv.client_id = ?";
    $params[] = $clientId;
}

$sql .= " ORDER BY vv.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
