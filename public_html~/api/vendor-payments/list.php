<?php
// =====================================================
// GET /api/vendor-payments/list.php?vendor_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$vendorId = $_GET['vendor_id'] ?? null;

$sql = "SELECT vp.*, v.name as vendor_name
        FROM vendor_payments vp
        JOIN vendors v ON v.id = vp.vendor_id
        WHERE 1=1";
$params = [];

if ($vendorId) {
    $sql .= " AND vp.vendor_id = ?";
    $params[] = $vendorId;
}

$sql .= " ORDER BY vp.payment_date DESC, vp.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
