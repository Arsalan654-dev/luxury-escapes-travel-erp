<?php
// =====================================================
// GET /api/dashboard/trend.php
// Returns daily hotel invoice totals for the last 30 days,
// used to draw the Account Statistics style chart.
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$startDate = date('Y-m-d', strtotime('-29 days'));

$stmt = $pdo->prepare("
    SELECT DATE(created_at) as day, COUNT(*) as bookings, COALESCE(SUM(total_amount),0) as total
    FROM hotel_invoices
    WHERE DATE(created_at) >= ?
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$stmt->execute([$startDate]);
$rows = $stmt->fetchAll();

// Fill in every day (even zero-activity days) so the chart has a continuous line
$byDay = [];
foreach ($rows as $r) {
    $byDay[$r['day']] = ['bookings' => (int)$r['bookings'], 'total' => (float)$r['total']];
}

$series = [];
for ($i = 0; $i < 30; $i++) {
    $day = date('Y-m-d', strtotime("-" . (29 - $i) . " days"));
    $series[] = [
        'date' => $day,
        'bookings' => $byDay[$day]['bookings'] ?? 0,
        'total' => $byDay[$day]['total'] ?? 0,
    ];
}

echo json_encode(['status' => 'success', 'series' => $series]);
