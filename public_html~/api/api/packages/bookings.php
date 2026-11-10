<?php
// =====================================================
// GET /api/packages/bookings.php?client_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$clientId = $_GET['client_id'] ?? null;

$sql = "SELECT pb.*, p.name as package_name, p.destination, c.name as client_name
        FROM package_bookings pb
        JOIN packages p ON p.id = pb.package_id
        JOIN clients c ON c.id = pb.client_id
        WHERE 1=1";
$params = [];
if ($clientId) {
    $sql .= " AND pb.client_id = ?";
    $params[] = $clientId;
}
$sql .= " ORDER BY pb.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

foreach ($bookings as &$b) {
    $stmt2 = $pdo->prepare("SELECT guest_name, visa_invoice_id FROM package_booking_travelers WHERE package_booking_id = ?");
    $stmt2->execute([$b['id']]);
    $b['travelers'] = $stmt2->fetchAll();
}
unset($b);

echo json_encode(['status' => 'success', 'data' => $bookings]);
