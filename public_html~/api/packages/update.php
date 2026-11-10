<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Package id required']); exit; }

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE packages SET name=?, destination=?, description=?,
        visa_type=?, visa_country=?, visa_price_per_person=?, num_visas=?, visa_vendor_id=?,
        num_rooms=?, currency_code=?, is_active=? WHERE id=?");
    $stmt->execute([
        $input['name'], $input['destination'], $input['description'] ?? null,
        $input['visa_type'] ?? null, $input['visa_country'] ?? null,
        $input['visa_price_per_person'] ?? 0,
        max(1, intval($input['num_visas'] ?? 1)),
        $input['visa_vendor_id'] ?: null,
        max(1, intval($input['num_rooms'] ?? 1)),
        $input['currency_code'] ?? 'SAR', $input['is_active'] ?? 1, $id,
    ]);

    // Replace hotels wholesale
    $pdo->prepare("DELETE FROM package_hotels WHERE package_id = ?")->execute([$id]);
    if (!empty($input['hotels']) && is_array($input['hotels'])) {
        $hStmt = $pdo->prepare("INSERT INTO package_hotels
            (package_id, hotel_name, hotel_city, hotel_address, hotel_star_rating, room_type, num_rooms,
             nights, meal_plan, price_per_night, checkin_time, checkout_time, hotel_vendor_id, hotel_contact, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($input['hotels'] as $i => $h) {
            if (empty(trim($h['hotel_name'] ?? ''))) continue;
            $hStmt->execute([
                $id, trim($h['hotel_name']), $h['hotel_city'] ?? null, $h['hotel_address'] ?? null,
                $h['hotel_star_rating'] ?: null, $h['room_type'] ?? 'Triple',
                max(1, intval($h['num_rooms'] ?? 1)),
                max(1, intval($h['nights'] ?? 1)),
                $h['meal_plan'] ?? 'Room Only', $h['price_per_night'] ?? 0,
                $h['checkin_time'] ?? '1600 HRS', $h['checkout_time'] ?? '1200 NOON',
                $h['hotel_vendor_id'] ?: null, $h['hotel_contact'] ?? null, $i + 1,
            ]);
        }
    }

    // Replace transports wholesale
    $pdo->prepare("DELETE FROM package_transports WHERE package_id = ?")->execute([$id]);
    if (!empty($input['transports']) && is_array($input['transports'])) {
        $tStmt = $pdo->prepare("INSERT INTO package_transports
            (package_id, route, vehicle, price_per_person, transport_vendor_id, sort_order)
            VALUES (?,?,?,?,?,?)");
        foreach ($input['transports'] as $i => $t) {
            if (empty(trim($t['route'] ?? ''))) continue;
            $tStmt->execute([
                $id, trim($t['route']), $t['vehicle'] ?? null,
                $t['price_per_person'] ?? 0, $t['transport_vendor_id'] ?: null, $i + 1,
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Package updated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update package', 'details' => $e->getMessage()]);
}
