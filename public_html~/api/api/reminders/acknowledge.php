<?php
// =====================================================
// POST /api/reminders/acknowledge.php
// Body: { ref_type, ref_id }
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = getDbConnection();
$user = requireAuth($pdo);
$input = jsonInput();

$refType = $input['ref_type'] ?? null;
$refId = $input['ref_id'] ?? null;

if (!$refType || !$refId || !in_array($refType, ['hotel', 'transport'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ref_type and ref_id are required']);
    exit;
}

$remark = $input['remark'] ?? null;

$stmt = $pdo->prepare("INSERT INTO reminder_acknowledgements (ref_type, ref_id, acknowledged_by, remark) VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE remark = VALUES(remark)");
$stmt->execute([$refType, $refId, $user['id'], $remark]);

echo json_encode(['status' => 'success', 'message' => 'Marked done']);
