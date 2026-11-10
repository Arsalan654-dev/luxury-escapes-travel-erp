<?php
// =====================================================
// POST /api/hotel-invoices/update.php
// Body: { id, ...same fields as create.php, passengers: [...], flights: [...] }
// If total_amount changes, the linked ledger entry is updated too.
// passengers/flights are replaced wholesale (delete + reinsert) each save.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/voucher_helper.php';

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
    echo json_encode(['error' => 'Invoice id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM hotel_invoices WHERE id = ?");
$stmt->execute([$id]);
$existing = $stmt->fetch();
if (!$existing) {
    http_response_code(404);
    echo json_encode(['error' => 'Hotel invoice not found']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        UPDATE hotel_invoices SET
            vendor_id = ?, transaction_type = ?, booking_status = ?, season = ?,
            hotel_name = ?, hotel_city = ?, hotel_address = ?, hotel_star_rating = ?,
            room_type = ?, room_view = ?, pax = ?,
            meal_plan = ?, check_in = ?, check_out = ?, nights = ?, price_per_night = ?,
            sub_total = ?, municipality_fee_pct = ?, vat_pct = ?, vat_amount = ?,
            total_amount = ?, currency_code = ?, checkin_time = ?, checkout_time = ?,
            group_visa_no = ?, visa_executive = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['vendor_id'] ?: null,
        $input['transaction_type'] ?? $existing['transaction_type'],
        $input['booking_status'] ?? $existing['booking_status'],
        $input['season'] ?? null,
        $input['hotel_name'],
        $input['hotel_city'] ?? null,
        $input['hotel_address'] ?? null,
        $input['hotel_star_rating'] ?: null,
        $input['room_type'],
        $input['room_view'] ?? null,
        $input['pax'],
        $input['meal_plan'] ?? null,
        $input['check_in'],
        $input['check_out'],
        $input['nights'],
        $input['price_per_night'],
        $input['sub_total'] ?? $input['total_amount'],
        $input['municipality_fee_pct'] ?? 0,
        $input['vat_pct'] ?? 15,
        $input['vat_amount'] ?? 0,
        $input['total_amount'],
        $input['currency_code'] ?? 'SAR',
        $input['checkin_time'] ?? '1600 HRS',
        $input['checkout_time'] ?? '1200 NOON',
        $input['group_visa_no'] ?: null,
        $input['visa_executive'] ?: null,
        $input['notes'] ?? null,
        $id,
    ]);

    // --- Replace passengers wholesale ---
    if (array_key_exists('passengers', $input)) {
        $pdo->prepare("DELETE FROM hotel_invoice_passengers WHERE hotel_invoice_id = ?")->execute([$id]);
        if (is_array($input['passengers'])) {
            $pStmt = $pdo->prepare("
                INSERT INTO hotel_invoice_passengers (hotel_invoice_id, name, passport_no, pax_type, room_allocation)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($input['passengers'] as $p) {
                $name = trim($p['name'] ?? '');
                if ($name === '') continue;
                $paxType = in_array($p['pax_type'] ?? '', ['adult_male', 'adult_female', 'child', 'infant'])
                    ? $p['pax_type'] : 'adult_male';
                $pStmt->execute([$id, $name, trim($p['passport_no'] ?? '') ?: null, $paxType, trim($p['room_allocation'] ?? '') ?: null]);
            }
        }
    }

    // --- Replace flights wholesale ---
    if (array_key_exists('flights', $input)) {
        $pdo->prepare("DELETE FROM hotel_invoice_flights WHERE hotel_invoice_id = ?")->execute([$id]);
        if (is_array($input['flights'])) {
            $fStmt = $pdo->prepare("
                INSERT INTO hotel_invoice_flights
                (hotel_invoice_id, pnr, flight_no, from_airport, to_airport, flight_date, departure_time, arrival_time, leg_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($input['flights'] as $i => $f) {
                $flightNo = trim($f['flight_no'] ?? '') ?: null;
                $pnr = trim($f['pnr'] ?? '') ?: null;
                $from = trim($f['from_airport'] ?? '') ?: null;
                $to = trim($f['to_airport'] ?? '') ?: null;
                if (!$flightNo && !$pnr && !$from && !$to) continue;
                $fStmt->execute([
                    $id, $pnr, $flightNo, $from, $to,
                    trim($f['flight_date'] ?? '') ?: null,
                    trim($f['departure_time'] ?? '') ?: null,
                    trim($f['arrival_time'] ?? '') ?: null,
                    $i + 1,
                ]);
            }
        }
    }

    // Update the linked ledger entry to match the new total
    $stmt = $pdo->prepare("SELECT id, client_id, voucher_no FROM ledger_entries WHERE linked_hotel_invoice_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $ledgerRow = $stmt->fetch();

    if ($ledgerRow) {
        $narration = $input['hotel_name'] . ' - ' . $input['room_type'] . ' - ' . $input['pax'] . ' PAX - ' .
                     date('d M', strtotime($input['check_in'])) . ' to ' . date('d M Y', strtotime($input['check_out']));

        $upd = $pdo->prepare("UPDATE ledger_entries SET entry_date = ?, narration = ?, debit = ?, is_edited = 1, updated_by = ? WHERE id = ?");
        $upd->execute([$input['check_in'], $narration, $input['total_amount'], $user['id'], $ledgerRow['id']]);
        recalcClientLedger($pdo, $ledgerRow['client_id']);
    }

    // Sync the vendor payable ledger too — create it if it's now a
    // purchase-with-vendor for the first time, update it if it already exists.
    $narration = $input['hotel_name'] . ' - ' . $input['room_type'] . ' - ' . $input['pax'] . ' PAX - ' .
                 date('d M', strtotime($input['check_in'])) . ' to ' . date('d M Y', strtotime($input['check_out']));
    $stmt = $pdo->prepare("SELECT id, vendor_id FROM vendor_ledger_entries WHERE linked_hotel_invoice_id = ? LIMIT 1");
    $stmt->execute([$id]);
    $vendorLedgerRow = $stmt->fetch();
    $newTransactionType = $input['transaction_type'] ?? $existing['transaction_type'];

    if ($vendorLedgerRow) {
        if (!empty($input['vendor_id']) && $newTransactionType === 'purchase') {
            $upd = $pdo->prepare("UPDATE vendor_ledger_entries SET entry_date = ?, narration = ?, credit = ?, is_edited = 1, updated_by = ? WHERE id = ?");
            $upd->execute([$input['check_in'], $narration, $input['total_amount'], $user['id'], $vendorLedgerRow['id']]);
            recalcVendorLedger($pdo, $input['vendor_id']);
        } else {
            // No longer a vendor purchase — remove the stale payable entry
            $pdo->prepare("DELETE FROM vendor_ledger_entries WHERE id = ?")->execute([$vendorLedgerRow['id']]);
            recalcVendorLedger($pdo, $vendorLedgerRow['vendor_id']);
        }
    } elseif (!empty($input['vendor_id']) && $newTransactionType === 'purchase') {
        addVendorLedgerEntry($pdo, [
            'vendor_id' => $input['vendor_id'],
            'entry_date' => $input['check_in'],
            'voucher_type' => 'FI',
            'voucher_no' => $ledgerRow['voucher_no'] ?? ('FI-' . $id),
            'narration' => $narration,
            'linked_hotel_invoice_id' => $id,
            'credit' => $input['total_amount'],
            'debit' => 0,
            'currency_code' => $input['currency_code'] ?? 'SAR',
            'created_by' => $user['id'],
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Hotel invoice updated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update hotel invoice', 'details' => $e->getMessage()]);
}
