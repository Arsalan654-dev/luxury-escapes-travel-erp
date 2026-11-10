<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/voucher_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$required = ['client_id', 'hotel_name', 'room_type', 'pax', 'check_in', 'check_out', 'nights', 'price_per_night', 'total_amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400); echo json_encode(['error' => "Field '$field' is required"]); exit;
    }
}

$pdo->beginTransaction();
try {
    $bookingRef = $input['booking_ref'] ?? ('RT' . rand(10000, 99999));
    $transactionType = $input['transaction_type'] ?? 'purchase';

    // Build columns dynamically based on what the DB actually has
    $cols = 'vendor_id, client_id, transaction_type, booking_ref, booking_status, season,
         hotel_name, hotel_city, room_type, room_view, pax, meal_plan,
         check_in, check_out, nights, price_per_night, sub_total,
         municipality_fee_pct, vat_pct, vat_amount, total_amount, currency_code,
         printed_by, notes, created_by';
    $vals = [
        $input['vendor_id'] ?: null, $input['client_id'], $transactionType, $bookingRef,
        $input['booking_status'] ?? 'confirm', $input['season'] ?? null,
        $input['hotel_name'], $input['hotel_city'] ?? null,
        $input['room_type'], $input['room_view'] ?? null, $input['pax'], $input['meal_plan'] ?? null,
        $input['check_in'], $input['check_out'], $input['nights'], $input['price_per_night'],
        $input['sub_total'] ?? $input['total_amount'],
        $input['municipality_fee_pct'] ?? 0, $input['vat_pct'] ?? 15, $input['vat_amount'] ?? 0,
        $input['total_amount'], $input['currency_code'] ?? 'SAR',
        $user['name'], $input['notes'] ?? null, $user['id'],
    ];

    // Optionally add new columns if they exist in the DB
    $extraCols = ['hotel_address', 'hotel_star_rating', 'checkin_time', 'checkout_time', 'group_visa_no', 'visa_executive'];
    $extraDefaults = [null, null, '1600 HRS', '1200 NOON', null, null];
    $hasExtras = false;
    try {
        $pdo->query("SELECT hotel_address FROM hotel_invoices LIMIT 0");
        $hasExtras = true;
    } catch (Exception $e) {}

    if ($hasExtras) {
        $cols .= ', hotel_address, hotel_star_rating, checkin_time, checkout_time, group_visa_no, visa_executive';
        $vals[] = $input['hotel_address'] ?? null;
        $vals[] = $input['hotel_star_rating'] ?: null;
        $vals[] = $input['checkin_time'] ?? '1600 HRS';
        $vals[] = $input['checkout_time'] ?? '1200 NOON';
        $vals[] = $input['group_visa_no'] ?: null;
        $vals[] = $input['visa_executive'] ?: null;
    }

    $placeholders = implode(',', array_fill(0, count($vals), '?'));
    $stmt = $pdo->prepare("INSERT INTO hotel_invoices ($cols) VALUES ($placeholders)");
    $stmt->execute($vals);
    $invoiceId = $pdo->lastInsertId();

    // Passengers (safe — skip if table doesn't exist)
    if (!empty($input['passengers']) && is_array($input['passengers'])) {
        try {
            $pStmt = $pdo->prepare("INSERT INTO hotel_invoice_passengers (hotel_invoice_id, name, passport_no, pax_type, room_allocation) VALUES (?, ?, ?, ?, ?)");
            foreach ($input['passengers'] as $p) {
                $name = trim($p['name'] ?? '');
                if ($name === '') continue;
                $paxType = in_array($p['pax_type'] ?? '', ['adult_male','adult_female','child','infant']) ? $p['pax_type'] : 'adult_male';
                $pStmt->execute([$invoiceId, $name, trim($p['passport_no'] ?? '') ?: null, $paxType, trim($p['room_allocation'] ?? '') ?: null]);
            }
        } catch (Exception $e) { /* table doesn't exist yet — skip silently */ }
    }

    // Flights (safe)
    if (!empty($input['flights']) && is_array($input['flights'])) {
        try {
            $fStmt = $pdo->prepare("INSERT INTO hotel_invoice_flights (hotel_invoice_id, pnr, flight_no, from_airport, to_airport, flight_date, departure_time, arrival_time, leg_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($input['flights'] as $i => $f) {
                $fn = trim($f['flight_no'] ?? '') ?: null;
                $pnr = trim($f['pnr'] ?? '') ?: null;
                $from = trim($f['from_airport'] ?? '') ?: null;
                $to = trim($f['to_airport'] ?? '') ?: null;
                if (!$fn && !$pnr && !$from && !$to) continue;
                $fStmt->execute([$invoiceId, $pnr, $fn, $from, $to, trim($f['flight_date'] ?? '') ?: null, trim($f['departure_time'] ?? '') ?: null, trim($f['arrival_time'] ?? '') ?: null, $i+1]);
            }
        } catch (Exception $e) { /* table doesn't exist yet */ }
    }

    $voucherNo = nextVoucherNumber($pdo, 'FI');
    $narration = $input['hotel_name'] . ' - ' . $input['room_type'] . ' - ' . $input['pax'] . ' PAX - ' .
                 date('d M', strtotime($input['check_in'])) . ' to ' . date('d M Y', strtotime($input['check_out']));

    addLedgerEntry($pdo, [
        'client_id' => $input['client_id'], 'entry_date' => $input['check_in'],
        'voucher_type' => 'FI', 'voucher_no' => $voucherNo, 'narration' => $narration,
        'linked_hotel_invoice_id' => $invoiceId, 'debit' => $input['total_amount'], 'credit' => 0,
        'currency_code' => $input['currency_code'] ?? 'SAR', 'created_by' => $user['id'],
    ]);

    if (!empty($input['vendor_id']) && $transactionType === 'purchase') {
        addVendorLedgerEntry($pdo, [
            'vendor_id' => $input['vendor_id'], 'entry_date' => $input['check_in'],
            'voucher_type' => 'FI', 'voucher_no' => $voucherNo, 'narration' => $narration,
            'linked_hotel_invoice_id' => $invoiceId, 'credit' => $input['total_amount'], 'debit' => 0,
            'currency_code' => $input['currency_code'] ?? 'SAR', 'created_by' => $user['id'],
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Hotel invoice created and posted to ledger', 'id' => $invoiceId, 'booking_ref' => $bookingRef, 'voucher_no' => $voucherNo]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create hotel invoice', 'details' => $e->getMessage()]);
}
