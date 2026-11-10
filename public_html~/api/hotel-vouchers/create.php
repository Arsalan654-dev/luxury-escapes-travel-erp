<?php
// =====================================================
// POST /api/hotel-vouchers/create.php
// Body: { hotel_invoice_id, guest_name, hotel_cnf_no, transport_ref, mazarat, terms_conditions }
//
// Generates the client-facing voucher sent AFTER the hotel is finalized.
// Contains hotel/booking details only — no price shown to the client.
// Passenger (passport/pax type/room) and flight details are NOT copied —
// they're pulled live from hotel_invoice_passengers / hotel_invoice_flights
// via hotel_invoice_id whenever the voucher is viewed/printed, so edits to
// the invoice's traveler list always show up on the voucher automatically.
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

$invoiceId = $input['hotel_invoice_id'] ?? null;
if (!$invoiceId) {
    http_response_code(400);
    echo json_encode(['error' => 'hotel_invoice_id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM hotel_invoices WHERE id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    echo json_encode(['error' => 'Hotel invoice not found']);
    exit;
}

// Check if a voucher already exists for this invoice — if so, just return it
// instead of erroring on the unique constraint.
$stmt = $pdo->prepare("SELECT id, voucher_ref FROM hotel_vouchers WHERE hotel_invoice_id = ? LIMIT 1");
$stmt->execute([$invoiceId]);
$existing = $stmt->fetch();
if ($existing) {
    echo json_encode(['status' => 'success', 'message' => 'Voucher already exists for this invoice', 'id' => $existing['id'], 'voucher_ref' => $existing['voucher_ref']]);
    exit;
}

$voucherRef = $invoice['booking_ref'];

$stmt = $pdo->prepare("
    INSERT INTO hotel_vouchers
    (voucher_ref, hotel_invoice_id, client_id, guest_name, hotel_name, hotel_cnf_no,
     city, hotel_address, hotel_star_rating, checkin_time, checkout_time, group_visa_no,
     transport_ref, mazarat, room_type, check_in, check_out, nights, pax,
     meal_plan, booking_status, terms_conditions, created_by_name, printed_by_name)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $voucherRef,
    $invoiceId,
    $invoice['client_id'],
    $input['guest_name'] ?? '',
    $invoice['hotel_name'],
    $input['hotel_cnf_no'] ?? null,
    $invoice['hotel_city'],
    $invoice['hotel_address'],
    $invoice['hotel_star_rating'],
    $invoice['checkin_time'],
    $invoice['checkout_time'],
    $invoice['group_visa_no'],
    $input['transport_ref'] ?? null,
    $input['mazarat'] ?? null,
    $invoice['room_type'],
    $invoice['check_in'],
    $invoice['check_out'],
    $invoice['nights'],
    $invoice['pax'],
    $invoice['meal_plan'],
    $invoice['booking_status'],
    $input['terms_conditions'] ?? null,
    $user['name'],
    $user['name'],
]);

echo json_encode(['status' => 'success', 'message' => 'Client voucher generated', 'id' => $pdo->lastInsertId(), 'voucher_ref' => $voucherRef]);
