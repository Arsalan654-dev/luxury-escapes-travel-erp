<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$stmt = $pdo->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY created_at DESC");
$packages = $stmt->fetchAll();

// Check if new tables exist
$hasMulti = false;
try { $pdo->query("SELECT 1 FROM package_hotels LIMIT 0"); $hasMulti = true; } catch (Exception $e) {}

if ($hasMulti) {
    $hStmt = $pdo->prepare("SELECT ph.*, v.name as vendor_name FROM package_hotels ph LEFT JOIN vendors v ON v.id = ph.hotel_vendor_id WHERE ph.package_id = ? ORDER BY ph.sort_order");
    $tStmt = $pdo->prepare("SELECT pt.*, v.name as vendor_name FROM package_transports pt LEFT JOIN vendors v ON v.id = pt.transport_vendor_id WHERE pt.package_id = ? ORDER BY pt.sort_order");

    foreach ($packages as &$p) {
        $hStmt->execute([$p['id']]);
        $p['hotels'] = $hStmt->fetchAll();
        $tStmt->execute([$p['id']]);
        $p['transports'] = $tStmt->fetchAll();
    }
    unset($p);
} else {
    // Fallback: build from flat columns
    foreach ($packages as &$p) {
        $p['hotels'] = [];
        if (!empty($p['hotel_name'])) {
            $p['hotels'][] = [
                'hotel_name' => $p['hotel_name'], 'hotel_city' => $p['hotel_city'],
                'room_type' => $p['room_type'], 'nights' => $p['nights'],
                'meal_plan' => $p['meal_plan'], 'price_per_night' => $p['hotel_price_per_night'],
                'hotel_vendor_id' => $p['hotel_vendor_id'], 'vendor_name' => null,
            ];
        }
        $p['transports'] = [];
        if (!empty($p['transport_route'])) {
            $p['transports'][] = [
                'route' => $p['transport_route'], 'vehicle' => $p['transport_vehicle'],
                'price_per_person' => $p['transport_price'],
                'transport_vendor_id' => $p['transport_vendor_id'], 'vendor_name' => null,
            ];
        }
    }
    unset($p);
}

// Also attach visa vendor name
$vStmt = $pdo->prepare("SELECT name FROM vendors WHERE id = ?");
foreach ($packages as &$p) {
    if (!empty($p['visa_vendor_id'])) {
        $vStmt->execute([$p['visa_vendor_id']]);
        $r = $vStmt->fetch();
        $p['visa_vendor_name'] = $r ? $r['name'] : null;
    } else {
        $p['visa_vendor_name'] = null;
    }
}
unset($p);

echo json_encode(['status' => 'success', 'data' => $packages]);
