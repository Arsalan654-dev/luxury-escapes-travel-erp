<?php
// =====================================================
// POST /api/packages/book.php — Multi-hotel + Multi-transport
// Creates one invoice per hotel, one per transport leg, one visa per traveler.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/voucher_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$packageId = $input['package_id'] ?? null;
$clientId = $input['client_id'] ?? null;

// Normalize travelers
$travelers = [];
if (!empty($input['travelers']) && is_array($input['travelers'])) {
    foreach ($input['travelers'] as $t) {
        $name = trim($t['name'] ?? '');
        if ($name === '') continue;
        $travelers[] = [
            'name' => $name,
            'passport_no' => trim($t['passport_no'] ?? '') ?: null,
            'pax_type' => in_array($t['pax_type'] ?? '', ['adult_male','adult_female','child','infant']) ? $t['pax_type'] : 'adult_male',
            'room_allocation' => trim($t['room_allocation'] ?? '') ?: null,
        ];
    }
} elseif (!empty($input['guest_names']) && is_array($input['guest_names'])) {
    foreach ($input['guest_names'] as $name) {
        $name = trim($name);
        if ($name === '') continue;
        $travelers[] = ['name'=>$name,'passport_no'=>null,'pax_type'=>'adult_male','room_allocation'=>null];
    }
}

if (!$packageId || !$clientId || empty($travelers)) {
    http_response_code(400);
    echo json_encode(['error' => 'package_id, client_id, and at least one traveler required']);
    exit;
}
$guestNames = array_column($travelers, 'name');

$stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$stmt->execute([$packageId]);
$pkg = $stmt->fetch();
if (!$pkg) { http_response_code(404); echo json_encode(['error' => 'Package not found']); exit; }

// Load hotels + transports from new tables (fallback to flat columns)
$hotels = []; $transports = [];
$hasMulti = false;
try { $pdo->query("SELECT 1 FROM package_hotels LIMIT 0"); $hasMulti = true; } catch (Exception $e) {}

if ($hasMulti) {
    $stmt = $pdo->prepare("SELECT * FROM package_hotels WHERE package_id = ? ORDER BY sort_order");
    $stmt->execute([$packageId]);
    $hotels = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT * FROM package_transports WHERE package_id = ? ORDER BY sort_order");
    $stmt->execute([$packageId]);
    $transports = $stmt->fetchAll();
}
// Fallback to flat columns if new tables are empty
if (empty($hotels) && !empty($pkg['hotel_name'])) {
    $hotels[] = [
        'hotel_name'=>$pkg['hotel_name'],'hotel_city'=>$pkg['hotel_city'],'hotel_address'=>$pkg['hotel_address'] ?? null,
        'hotel_star_rating'=>$pkg['hotel_star_rating'] ?? null,'room_type'=>$pkg['room_type'],'nights'=>$pkg['nights'],
        'meal_plan'=>$pkg['meal_plan'],'price_per_night'=>$pkg['hotel_price_per_night'],
        'hotel_vendor_id'=>$pkg['hotel_vendor_id'],'checkin_time'=>$pkg['checkin_time'] ?? '1600 HRS',
        'checkout_time'=>$pkg['checkout_time'] ?? '1200 NOON','hotel_contact'=>null,
    ];
}
if (empty($transports) && !empty($pkg['transport_route'])) {
    $transports[] = [
        'route'=>$pkg['transport_route'],'vehicle'=>$pkg['transport_vehicle'],
        'price_per_person'=>$pkg['transport_price'],'transport_vendor_id'=>$pkg['transport_vendor_id'],
    ];
}

// Apply booking-time price overrides from frontend (if sent)
$hotelsOverride = is_array($input['hotels_override'] ?? null) ? $input['hotels_override'] : [];
$transportsOverride = is_array($input['transports_override'] ?? null) ? $input['transports_override'] : [];
$visaPriceOverride = $input['visa_price_override'] ?? null;

foreach ($hotels as $i => &$h) {
    if (isset($hotelsOverride[$i])) {
        if (isset($hotelsOverride[$i]['nights'])) $h['nights'] = $hotelsOverride[$i]['nights'];
        if (isset($hotelsOverride[$i]['price_per_night'])) $h['price_per_night'] = $hotelsOverride[$i]['price_per_night'];
    }
}
unset($h);

foreach ($transports as $i => &$t) {
    if (isset($transportsOverride[$i])) {
        if (isset($transportsOverride[$i]['price_per_person'])) $t['price_per_person'] = $transportsOverride[$i]['price_per_person'];
    }
}
unset($t);

$visaPricePerPerson = ($visaPriceOverride !== null && $visaPriceOverride !== '') ? $visaPriceOverride : $visaPricePerPerson;

$numTravelers = count($travelers);
$transactionType = $input['transaction_type'] ?? 'purchase';
$checkIn = $input['check_in'] ?? date('Y-m-d');
$pickupDate = $input['pickup_date'] ?? $checkIn;
$pickupTime = $input['pickup_time'] ?? null;
$currency = $pkg['currency_code'];
$bookingStatus = $input['booking_status'] ?? 'confirm';
$groupVisaNo = trim($input['group_visa_no'] ?? '') ?: null;
$visaExecutive = trim($input['visa_executive'] ?? '') ?: null;
$transportRefNo = trim($input['transport_ref_no'] ?? '') ?: null;
$agentRefNo = trim($input['agent_ref_no'] ?? '') ?: null;
$flights = is_array($input['flights'] ?? null) ? $input['flights'] : [];

$pdo->beginTransaction();
try {
    $stmt = $pdo->query("SELECT COUNT(*) c FROM package_bookings");
    $groupRef = 'PKG-' . str_pad($stmt->fetch()['c'] + 1, 4, '0', STR_PAD_LEFT);

    $hotelInvoiceIds = [];
    $transportVoucherIds = [];

    // --- HOTEL INVOICES (one per hotel in the package) ---
    foreach ($hotels as $hotel) {
        $checkOut = date('Y-m-d', strtotime($checkIn . " +{$hotel['nights']} days"));
        $subTotal = $hotel['nights'] * $hotel['price_per_night'] * $numTravelers;
        $vat = round($subTotal * 0.15, 2);
        $total = $subTotal + $vat;
        $bookingRef = 'RT' . rand(10000, 99999);
        $vendorId = $input['hotel_vendor_id_' . ($hotel['id'] ?? '')] ?? ($hotel['hotel_vendor_id'] ?? null);

        $stmt = $pdo->prepare("
            INSERT INTO hotel_invoices
            (vendor_id, client_id, transaction_type, booking_ref, booking_status, season,
             hotel_name, hotel_city, room_type, pax, meal_plan, check_in, check_out, nights,
             price_per_night, sub_total, vat_pct, vat_amount, total_amount, currency_code,
             printed_by, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $vendorId, $clientId, $transactionType, $bookingRef, $bookingStatus, $pkg['name'],
            $hotel['hotel_name'], $hotel['hotel_city'], $hotel['room_type'], $numTravelers, $hotel['meal_plan'],
            $checkIn, $checkOut, $hotel['nights'], $hotel['price_per_night'], $subTotal, 15, $vat, $total,
            $currency, $user['name'], "Package {$groupRef}: {$hotel['hotel_name']}", $user['id'],
        ]);
        $invId = $pdo->lastInsertId();
        $hotelInvoiceIds[] = $invId;

        $voucherNo = nextVoucherNumber($pdo, 'FI');
        $narration = $hotel['hotel_name'] . ' - ' . $hotel['room_type'] . " - {$numTravelers} PAX - " . date('d M', strtotime($checkIn)) . ' to ' . date('d M Y', strtotime($checkOut));
        addLedgerEntry($pdo, ['client_id'=>$clientId,'entry_date'=>$checkIn,'voucher_type'=>'FI','voucher_no'=>$voucherNo,'narration'=>$narration,'linked_hotel_invoice_id'=>$invId,'debit'=>$total,'credit'=>0,'currency_code'=>$currency,'created_by'=>$user['id']]);
        if ($vendorId && $transactionType === 'purchase') {
            addVendorLedgerEntry($pdo, ['vendor_id'=>$vendorId,'entry_date'=>$checkIn,'voucher_type'=>'FI','voucher_no'=>$voucherNo,'narration'=>$narration,'linked_hotel_invoice_id'=>$invId,'credit'=>$total,'debit'=>0,'currency_code'=>$currency,'created_by'=>$user['id']]);
        }

        // Hotel client voucher
        $stmt = $pdo->prepare("INSERT INTO hotel_vouchers (voucher_ref, hotel_invoice_id, client_id, guest_name, hotel_name, city, room_type, check_in, check_out, nights, pax, meal_plan, booking_status, created_by_name, printed_by_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$bookingRef, $invId, $clientId, implode(', ', $guestNames), $hotel['hotel_name'], $hotel['hotel_city'], $hotel['room_type'], $checkIn, $checkOut, $hotel['nights'], $numTravelers, $hotel['meal_plan'], $bookingStatus, $user['name'], $user['name']]);

        // Shift check_in for next hotel
        $checkIn = $checkOut;
    }

    // --- TRANSPORT VOUCHERS (one per transport leg) ---
    foreach ($transports as $tr) {
        $amount = $tr['price_per_person'] * $numTravelers;
        $vendorId = $input['transport_vendor_id_' . ($tr['id'] ?? '')] ?? ($tr['transport_vendor_id'] ?? null);
        $voucherNo = nextVoucherNumber($pdo, 'TN');

        $stmt = $pdo->prepare("INSERT INTO transport_vouchers (voucher_no, vendor_id, client_id, transaction_type, route, vehicle_type, pickup_date, contact_person, total_pax, amount, currency_code, booking_notes, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$voucherNo, $vendorId, $clientId, $transactionType, $tr['route'], $tr['vehicle'], $pickupDate, implode(', ', $guestNames), $numTravelers, $amount, $currency, "Package {$groupRef}", 'confirmed', $user['id']]);
        $trId = $pdo->lastInsertId();
        $transportVoucherIds[] = $trId;

        $narration = $tr['route'] . ' Transport - ' . ($tr['vehicle'] ?? '') . ' - ' . date('d M Y', strtotime($pickupDate));
        addLedgerEntry($pdo, ['client_id'=>$clientId,'entry_date'=>$pickupDate,'voucher_type'=>'TN','voucher_no'=>$voucherNo,'narration'=>$narration,'linked_transport_voucher_id'=>$trId,'debit'=>$amount,'credit'=>0,'currency_code'=>$currency,'created_by'=>$user['id']]);
        if ($vendorId && $transactionType === 'purchase') {
            addVendorLedgerEntry($pdo, ['vendor_id'=>$vendorId,'entry_date'=>$pickupDate,'voucher_type'=>'TN','voucher_no'=>$voucherNo,'narration'=>$narration,'linked_transport_voucher_id'=>$trId,'credit'=>$amount,'debit'=>0,'currency_code'=>$currency,'created_by'=>$user['id']]);
        }

        // Transport client voucher
        $stmt = $pdo->prepare("INSERT INTO transport_client_vouchers (voucher_ref, transport_voucher_id, client_id, guest_name, route, vehicle_type, pickup_date, pickup_time, ref_no, total_pax, booking_notes, status, created_by_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$voucherNo, $trId, $clientId, implode(', ', $guestNames), $tr['route'], $tr['vehicle'], $pickupDate, $pickupTime, $transportRefNo, $numTravelers, $pkg['description'], 'confirmed', $user['name']]);
    }

    // --- VISA (one per traveler — unchanged) ---
    $travelerVisaIds = [];
    if (!empty($pkg['visa_type'])) {
        $visaVendorId = $input['visa_vendor_id'] ?? $pkg['visa_vendor_id'];
        foreach ($travelers as $t) {
            $voucherNo = nextVoucherNumber($pdo, 'DN');
            $stmt = $pdo->prepare("INSERT INTO visa_invoices (vendor_id, client_id, voucher_no, transaction_type, visa_type, country, guest_name, issue_date, amount, currency_code, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$visaVendorId, $clientId, $voucherNo, $transactionType, $pkg['visa_type'], $pkg['visa_country'], $t['name'], $checkIn, $visaPricePerPerson, $currency, "Package {$groupRef}", $user['id']]);
            $visaId = $pdo->lastInsertId();
            $travelerVisaIds[$t['name']] = $visaId;

            $narration = $pkg['visa_type'] . ' - ' . $t['name'];
            addLedgerEntry($pdo, ['client_id'=>$clientId,'entry_date'=>$checkIn,'voucher_type'=>'DN','voucher_no'=>$voucherNo,'narration'=>$narration,'linked_visa_invoice_id'=>$visaId,'debit'=>$visaPricePerPerson,'credit'=>0,'currency_code'=>$currency,'created_by'=>$user['id']]);
            if ($visaVendorId && $transactionType === 'purchase') {
                addVendorLedgerEntry($pdo, ['vendor_id'=>$visaVendorId,'entry_date'=>$checkIn,'voucher_type'=>'DN','voucher_no'=>$voucherNo,'narration'=>$narration,'linked_visa_invoice_id'=>$visaId,'credit'=>$visaPricePerPerson,'debit'=>0,'currency_code'=>$currency,'created_by'=>$user['id']]);
            }
            $stmt = $pdo->prepare("INSERT INTO visa_client_vouchers (voucher_ref, visa_invoice_id, client_id, guest_name, visa_type, country, issue_date, created_by_name) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$voucherNo, $visaId, $clientId, $t['name'], $pkg['visa_type'], $pkg['visa_country'], $checkIn, $user['name']]);
        }
    }

    // --- Booking record ---
    $stmt = $pdo->prepare("INSERT INTO package_bookings (package_id, client_id, group_ref, num_travelers, check_in, pickup_date, pickup_time, hotel_invoice_id, transport_voucher_id, transaction_type, group_visa_no, visa_executive, transport_ref_no, agent_ref_no, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $packageId, $clientId, $groupRef, $numTravelers, $input['check_in'] ?? date('Y-m-d'), $pickupDate, $pickupTime,
        $hotelInvoiceIds[0] ?? null, $transportVoucherIds[0] ?? null, $transactionType,
        $groupVisaNo, $visaExecutive, $transportRefNo, $agentRefNo, $user['id'],
    ]);
    $bookingId = $pdo->lastInsertId();

    foreach ($travelers as $t) {
        $stmt = $pdo->prepare("INSERT INTO package_booking_travelers (package_booking_id, guest_name, visa_invoice_id, passport_no, pax_type, room_allocation) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$bookingId, $t['name'], $travelerVisaIds[$t['name']] ?? null, $t['passport_no'], $t['pax_type'], $t['room_allocation']]);
    }

    foreach ($flights as $i => $f) {
        $fn = trim($f['flight_no'] ?? '') ?: null; $pnr = trim($f['pnr'] ?? '') ?: null;
        $from = trim($f['from_airport'] ?? '') ?: null; $to = trim($f['to_airport'] ?? '') ?: null;
        if (!$fn && !$pnr && !$from && !$to) continue;
        $stmt = $pdo->prepare("INSERT INTO package_booking_flights (package_booking_id, pnr, flight_no, from_airport, to_airport, flight_date, departure_time, arrival_time, leg_order) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$bookingId, $pnr, $fn, $from, $to, trim($f['flight_date'] ?? '') ?: null, trim($f['departure_time'] ?? '') ?: null, trim($f['arrival_time'] ?? '') ?: null, $i+1]);
    }

    $totalHotels = count($hotelInvoiceIds);
    $totalTransports = count($transportVoucherIds);
    $pdo->commit();
    echo json_encode([
        'status'=>'success',
        'message'=>"Booked {$numTravelers} travelers — {$totalHotels} hotel invoice(s), {$totalTransports} transport voucher(s), {$numTravelers} visa invoice(s) created.",
        'group_ref'=>$groupRef, 'num_travelers'=>$numTravelers,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to book package', 'details' => $e->getMessage()]);
}
