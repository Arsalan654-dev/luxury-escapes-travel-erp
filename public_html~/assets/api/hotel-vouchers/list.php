<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$clientId = $_GET['client_id'] ?? null;

$sql = "SELECT hv.*, c.name as client_name, c.client_code
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
$sql .= " ORDER BY hv.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

// Safely attach passengers + flights from linked invoice
$hasPass = false; $hasFlight = false;
try { $pdo->query("SELECT 1 FROM hotel_invoice_passengers LIMIT 0"); $hasPass = true; } catch (Exception $e) {}
try { $pdo->query("SELECT 1 FROM hotel_invoice_flights LIMIT 0"); $hasFlight = true; } catch (Exception $e) {}

if ($hasPass) $ps = $pdo->prepare("SELECT name, passport_no, pax_type, room_allocation FROM hotel_invoice_passengers WHERE hotel_invoice_id = ?");
if ($hasFlight) $fs = $pdo->prepare("SELECT pnr, flight_no, from_airport, to_airport, flight_date, departure_time, arrival_time, leg_order FROM hotel_invoice_flights WHERE hotel_invoice_id = ? ORDER BY leg_order ASC");

foreach ($vouchers as &$v) {
    if ($hasPass) { $ps->execute([$v['hotel_invoice_id']]); $v['passengers'] = $ps->fetchAll(); } else { $v['passengers'] = []; }
    if ($hasFlight) { $fs->execute([$v['hotel_invoice_id']]); $v['flights'] = $fs->fetchAll(); } else { $v['flights'] = []; }
}
unset($v);

echo json_encode(['status' => 'success', 'data' => $vouchers]);
