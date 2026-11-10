<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$clientId = $_GET['client_id'] ?? null;
$vendorId = $_GET['vendor_id'] ?? null;

$sql = "SELECT hi.*, c.name as client_name, c.client_code, v.name as vendor_name
        FROM hotel_invoices hi
        JOIN clients c ON c.id = hi.client_id
        LEFT JOIN vendors v ON v.id = hi.vendor_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (hi.hotel_name LIKE ? OR hi.booking_ref LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($clientId) { $sql .= " AND hi.client_id = ?"; $params[] = $clientId; }
if ($vendorId) { $sql .= " AND hi.vendor_id = ?"; $params[] = $vendorId; }
$sql .= " ORDER BY hi.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Safely attach passengers + flights (graceful if tables don't exist yet)
$hasPass = false; $hasFlight = false;
try { $pdo->query("SELECT 1 FROM hotel_invoice_passengers LIMIT 0"); $hasPass = true; } catch (Exception $e) {}
try { $pdo->query("SELECT 1 FROM hotel_invoice_flights LIMIT 0"); $hasFlight = true; } catch (Exception $e) {}

if ($hasPass) $ps = $pdo->prepare("SELECT name, passport_no, pax_type, room_allocation FROM hotel_invoice_passengers WHERE hotel_invoice_id = ?");
if ($hasFlight) $fs = $pdo->prepare("SELECT pnr, flight_no, from_airport, to_airport, flight_date, departure_time, arrival_time, leg_order FROM hotel_invoice_flights WHERE hotel_invoice_id = ? ORDER BY leg_order ASC");

foreach ($invoices as &$inv) {
    if ($hasPass) { $ps->execute([$inv['id']]); $inv['passengers'] = $ps->fetchAll(); } else { $inv['passengers'] = []; }
    if ($hasFlight) { $fs->execute([$inv['id']]); $inv['flights'] = $fs->fetchAll(); } else { $inv['flights'] = []; }
}
unset($inv);

echo json_encode(['status' => 'success', 'data' => $invoices]);
