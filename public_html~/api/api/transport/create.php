<?php
// =====================================================
// POST /api/transport/create.php
// Body: { vendor_id, client_id, guest_id, transaction_type,
//         route, vehicle_type, pickup_date, pickup_time, flight_details,
//         contact_person, total_pax, amount, currency_code, booking_notes }
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

$required = ['client_id', 'route', 'pickup_date', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$pdo->beginTransaction();
try {
    $voucherNo = nextVoucherNumber($pdo, 'TN');
    $transactionType = $input['transaction_type'] ?? 'purchase';

    $stmt = $pdo->prepare("
        INSERT INTO transport_vouchers
        (voucher_no, vendor_id, client_id, guest_id, transaction_type, route, vehicle_type,
         pickup_date, pickup_time, flight_details, contact_person, total_pax, amount,
         currency_code, booking_notes, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $voucherNo,
        $input['vendor_id'] ?: null,
        $input['client_id'],
        $input['guest_id'] ?? null,
        $transactionType,
        $input['route'],
        $input['vehicle_type'] ?? null,
        $input['pickup_date'],
        $input['pickup_time'] ?? null,
        $input['flight_details'] ?? null,
        $input['contact_person'] ?? null,
        $input['total_pax'] ?? 1,
        $input['amount'],
        $input['currency_code'] ?? 'SAR',
        $input['booking_notes'] ?? null,
        $input['status'] ?? 'confirmed',
        $user['id'],
    ]);
    $transportId = $pdo->lastInsertId();

    $narration = $input['route'] . ' Transport - ' . ($input['vehicle_type'] ?? '') . ' - ' . date('d M Y', strtotime($input['pickup_date']));

    addLedgerEntry($pdo, [
        'client_id' => $input['client_id'],
        'entry_date' => $input['pickup_date'],
        'voucher_type' => 'TN',
        'voucher_no' => $voucherNo,
        'narration' => $narration,
        'linked_transport_voucher_id' => $transportId,
        'debit' => $input['amount'],
        'credit' => 0,
        'currency_code' => $input['currency_code'] ?? 'SAR',
        'created_by' => $user['id'],
    ]);

    if (!empty($input['vendor_id']) && $transactionType === 'purchase') {
        addVendorLedgerEntry($pdo, [
            'vendor_id' => $input['vendor_id'],
            'entry_date' => $input['pickup_date'],
            'voucher_type' => 'TN',
            'voucher_no' => $voucherNo,
            'narration' => $narration,
            'linked_transport_voucher_id' => $transportId,
            'credit' => $input['amount'],
            'debit' => 0,
            'currency_code' => $input['currency_code'] ?? 'SAR',
            'created_by' => $user['id'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Transport voucher created and posted to ledger',
        'id' => $transportId,
        'voucher_no' => $voucherNo,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create transport voucher', 'details' => $e->getMessage()]);
}
