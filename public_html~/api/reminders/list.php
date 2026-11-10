<?php
// =====================================================
// GET /api/reminders/list.php
// Returns hotel check-ins and transport pickups happening
// within the next 24 hours (or already overdue) that the
// CURRENT logged-in user has NOT personally marked done.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$cutoff = date('Y-m-d', strtotime('+1 day'));
$today = date('Y-m-d');

$reminders = [];

// Hotel check-ins
$stmt = $pdo->prepare("
    SELECT hi.id, hi.booking_ref, hi.hotel_name, hi.check_in, c.name as client_name
    FROM hotel_invoices hi
    JOIN clients c ON c.id = hi.client_id
    LEFT JOIN reminder_acknowledgements ra ON ra.ref_type = 'hotel' AND ra.ref_id = hi.id AND ra.acknowledged_by = ?
    WHERE hi.check_in <= ? AND hi.booking_status = 'confirm' AND ra.id IS NULL
    ORDER BY hi.check_in ASC
");
$stmt->execute([$user['id'], $cutoff]);
foreach ($stmt->fetchAll() as $r) {
    $daysLeft = (int)((strtotime($r['check_in']) - strtotime($today)) / 86400);
    $reminders[] = [
        'ref_type' => 'hotel',
        'ref_id' => (int)$r['id'],
        'title' => 'Hotel Check-in: ' . $r['hotel_name'],
        'subtitle' => $r['client_name'] . ' · ' . $r['booking_ref'],
        'deadline' => $r['check_in'],
        'days_left' => $daysLeft,
        'overdue' => $daysLeft < 0,
    ];
}

// Transport pickups
$stmt = $pdo->prepare("
    SELECT tv.id, tv.voucher_no, tv.route, tv.pickup_date, c.name as client_name
    FROM transport_vouchers tv
    JOIN clients c ON c.id = tv.client_id
    LEFT JOIN reminder_acknowledgements ra ON ra.ref_type = 'transport' AND ra.ref_id = tv.id AND ra.acknowledged_by = ?
    WHERE tv.pickup_date <= ? AND tv.status = 'confirmed' AND ra.id IS NULL
    ORDER BY tv.pickup_date ASC
");
$stmt->execute([$user['id'], $cutoff]);
foreach ($stmt->fetchAll() as $r) {
    $daysLeft = (int)((strtotime($r['pickup_date']) - strtotime($today)) / 86400);
    $reminders[] = [
        'ref_type' => 'transport',
        'ref_id' => (int)$r['id'],
        'title' => 'Transport Pickup: ' . $r['route'],
        'subtitle' => $r['client_name'] . ' · ' . $r['voucher_no'],
        'deadline' => $r['pickup_date'],
        'days_left' => $daysLeft,
        'overdue' => $daysLeft < 0,
    ];
}

usort($reminders, fn($a, $b) => strtotime($a['deadline']) - strtotime($b['deadline']));

echo json_encode(['status' => 'success', 'data' => $reminders]);
