<?php
// =====================================================
// POST /api/transport-vouchers/create.php
// Body: { transport_voucher_id, guest_name, terms_conditions? }
// Generates the client-facing voucher — no pricing shown.
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

$transportId = $input['transport_voucher_id'] ?? null;
if (!$transportId) {
    http_response_code(400);
    echo json_encode(['error' => 'transport_voucher_id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM transport_vouchers WHERE id = ?");
$stmt->execute([$transportId]);
$t = $stmt->fetch();

if (!$t) {
    http_response_code(404);
    echo json_encode(['error' => 'Transport booking not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, voucher_ref FROM transport_client_vouchers WHERE transport_voucher_id = ? LIMIT 1");
$stmt->execute([$transportId]);
$existing = $stmt->fetch();
if ($existing) {
    echo json_encode(['status' => 'success', 'message' => 'Voucher already exists for this booking', 'id' => $existing['id'], 'voucher_ref' => $existing['voucher_ref']]);
    exit;
}

$voucherRef = $t['voucher_no'];

$stmt = $pdo->prepare("
    INSERT INTO transport_client_vouchers
    (voucher_ref, transport_voucher_id, client_id, guest_name, route, vehicle_type,
     pickup_date, pickup_time, flight_details, contact_person, total_pax, booking_notes,
     status, created_by_name)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $voucherRef,
    $transportId,
    $t['client_id'],
    $input['guest_name'] ?? '',
    $t['route'],
    $t['vehicle_type'],
    $t['pickup_date'],
    $t['pickup_time'],
    $t['flight_details'],
    $t['contact_person'],
    $t['total_pax'],
    $input['terms_conditions'] ?? $t['booking_notes'],
    $t['status'],
    $user['name'],
]);

echo json_encode(['status' => 'success', 'message' => 'Client voucher generated', 'id' => $pdo->lastInsertId(), 'voucher_ref' => $voucherRef]);
