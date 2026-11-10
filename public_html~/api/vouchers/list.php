<?php
// =====================================================
// GET /api/vouchers/list.php?search=&type=&client_id=
// Combines hotel_vouchers, transport_client_vouchers, and
// visa_client_vouchers into one unified feed.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? null; // hotel | transport | visa
$clientId = $_GET['client_id'] ?? null;

$results = [];

if (!$type || $type === 'hotel') {
    $sql = "SELECT hv.id, 'hotel' as voucher_type, hv.voucher_ref, hv.client_id, c.name as client_name,
                   hv.guest_name, hv.hotel_name as title, hv.room_type as subtitle,
                   hv.check_in as date_field, hv.created_at
            FROM hotel_vouchers hv
            JOIN clients c ON c.id = hv.client_id
            WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (hv.hotel_name LIKE ? OR hv.guest_name LIKE ? OR hv.voucher_ref LIKE ? OR c.name LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($clientId) { $sql .= " AND hv.client_id = ?"; $params[] = $clientId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = array_merge($results, $stmt->fetchAll());
}

if (!$type || $type === 'transport') {
    $sql = "SELECT tv.id, 'transport' as voucher_type, tv.voucher_ref, tv.client_id, c.name as client_name,
                   tv.guest_name, tv.route as title, tv.vehicle_type as subtitle,
                   tv.pickup_date as date_field, tv.created_at
            FROM transport_client_vouchers tv
            JOIN clients c ON c.id = tv.client_id
            WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (tv.route LIKE ? OR tv.guest_name LIKE ? OR tv.voucher_ref LIKE ? OR c.name LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($clientId) { $sql .= " AND tv.client_id = ?"; $params[] = $clientId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = array_merge($results, $stmt->fetchAll());
}

if (!$type || $type === 'visa') {
    $sql = "SELECT vv.id, 'visa' as voucher_type, vv.voucher_ref, vv.client_id, c.name as client_name,
                   vv.guest_name, vv.visa_type as title, vv.country as subtitle,
                   vv.issue_date as date_field, vv.created_at
            FROM visa_client_vouchers vv
            JOIN clients c ON c.id = vv.client_id
            WHERE 1=1";
    $params = [];
    if ($search) {
        $sql .= " AND (vv.visa_type LIKE ? OR vv.guest_name LIKE ? OR vv.voucher_ref LIKE ? OR c.name LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($clientId) { $sql .= " AND vv.client_id = ?"; $params[] = $clientId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = array_merge($results, $stmt->fetchAll());
}

usort($results, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

echo json_encode(['status' => 'success', 'data' => $results, 'total' => count($results)]);
