<?php
// =====================================================
// GET /api/reminders/status.php  (admin only)
// For every active reminder (within 1 day / overdue), shows
// which active employees have marked it done and which haven't.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_users');

$cutoff = date('Y-m-d', strtotime('+1 day'));
$today = date('Y-m-d');

// All active employees
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name ASC");
$employees = $stmt->fetchAll();

$reminders = [];

$stmt = $pdo->prepare("
    SELECT hi.id, hi.booking_ref, hi.hotel_name, hi.check_in, c.name as client_name
    FROM hotel_invoices hi
    JOIN clients c ON c.id = hi.client_id
    WHERE hi.check_in <= ? AND hi.booking_status = 'confirm'
    ORDER BY hi.check_in ASC
");
$stmt->execute([$cutoff]);
foreach ($stmt->fetchAll() as $r) {
    $reminders[] = ['ref_type' => 'hotel', 'ref_id' => (int)$r['id'], 'title' => 'Hotel Check-in: ' . $r['hotel_name'], 'subtitle' => $r['client_name'] . ' · ' . $r['booking_ref'], 'deadline' => $r['check_in']];
}

$stmt = $pdo->prepare("
    SELECT tv.id, tv.voucher_no, tv.route, tv.pickup_date, c.name as client_name
    FROM transport_vouchers tv
    JOIN clients c ON c.id = tv.client_id
    WHERE tv.pickup_date <= ? AND tv.status = 'confirmed'
    ORDER BY tv.pickup_date ASC
");
$stmt->execute([$cutoff]);
foreach ($stmt->fetchAll() as $r) {
    $reminders[] = ['ref_type' => 'transport', 'ref_id' => (int)$r['id'], 'title' => 'Transport Pickup: ' . $r['route'], 'subtitle' => $r['client_name'] . ' · ' . $r['voucher_no'], 'deadline' => $r['pickup_date']];
}

// Get all acknowledgements for these reminders
$ackStmt = $pdo->query("SELECT ref_type, ref_id, acknowledged_by, acknowledged_at, remark FROM reminder_acknowledgements");
$acks = $ackStmt->fetchAll();

foreach ($reminders as &$rem) {
    $rem['employees'] = [];
    foreach ($employees as $emp) {
        $ack = null;
        foreach ($acks as $a) {
            if ($a['ref_type'] === $rem['ref_type'] && (int)$a['ref_id'] === $rem['ref_id'] && (int)$a['acknowledged_by'] === (int)$emp['id']) {
                $ack = $a;
                break;
            }
        }
        $rem['employees'][] = [
            'id' => $emp['id'],
            'name' => $emp['name'],
            'done' => $ack !== null,
            'done_at' => $ack['acknowledged_at'] ?? null,
            'remark' => $ack['remark'] ?? null,
        ];
    }
}
unset($rem);

usort($reminders, fn($a, $b) => strtotime($a['deadline']) - strtotime($b['deadline']));

echo json_encode(['status' => 'success', 'data' => $reminders]);
