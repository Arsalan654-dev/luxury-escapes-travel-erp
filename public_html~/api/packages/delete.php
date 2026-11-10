<?php
// =====================================================
// POST /api/packages/delete.php
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
    echo json_encode(['error' => 'Package id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM package_bookings WHERE package_id = ?");
$stmt->execute([$id]);
$hasBookings = $stmt->fetch()['c'] > 0;

if ($hasBookings) {
    $pdo->prepare("UPDATE packages SET is_active = 0 WHERE id = ?")->execute([$id]);
    echo json_encode(['status' => 'success', 'message' => 'Package has existing bookings, so it was deactivated instead of deleted.', 'action' => 'deactivated']);
} else {
    $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
    echo json_encode(['status' => 'success', 'message' => 'Package deleted', 'action' => 'deleted']);
}
