<?php
// =====================================================
// POST /api/visa-vouchers/delete.php
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
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Voucher id is required']);
    exit;
}

$pdo->prepare("DELETE FROM visa_client_vouchers WHERE id = ?")->execute([$id]);
echo json_encode(['status' => 'success', 'message' => 'Voucher deleted']);
