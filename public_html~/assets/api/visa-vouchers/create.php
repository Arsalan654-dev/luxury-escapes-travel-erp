<?php
// =====================================================
// POST /api/visa-vouchers/create.php
// Body: { visa_invoice_id, guest_name, notes? }
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
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$visaId = $input['visa_invoice_id'] ?? null;
if (!$visaId) {
    http_response_code(400);
    echo json_encode(['error' => 'visa_invoice_id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM visa_invoices WHERE id = ?");
$stmt->execute([$visaId]);
$v = $stmt->fetch();

if (!$v) {
    http_response_code(404);
    echo json_encode(['error' => 'Visa invoice not found']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, voucher_ref FROM visa_client_vouchers WHERE visa_invoice_id = ? LIMIT 1");
$stmt->execute([$visaId]);
$existing = $stmt->fetch();
if ($existing) {
    echo json_encode(['status' => 'success', 'message' => 'Voucher already exists for this invoice', 'id' => $existing['id'], 'voucher_ref' => $existing['voucher_ref']]);
    exit;
}

$voucherRef = $v['voucher_no'];

$stmt = $pdo->prepare("
    INSERT INTO visa_client_vouchers
    (voucher_ref, visa_invoice_id, client_id, guest_name, visa_type, country, issue_date, expiry_date, notes, created_by_name)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $voucherRef,
    $visaId,
    $v['client_id'],
    $input['guest_name'] ?? $v['guest_name'],
    $v['visa_type'],
    $v['country'],
    $v['issue_date'],
    $v['expiry_date'],
    $input['notes'] ?? null,
    $user['name'],
]);

echo json_encode(['status' => 'success', 'message' => 'Client voucher generated', 'id' => $pdo->lastInsertId(), 'voucher_ref' => $voucherRef]);
