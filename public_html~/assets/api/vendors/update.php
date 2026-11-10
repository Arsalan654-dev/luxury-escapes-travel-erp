<?php
// =====================================================
// POST /api/vendors/update.php
// Body: { id, name, vendor_type, contact_person, phone, email, address,
//         vat_number, bank_name, bank_account_name, bank_account_no,
//         bank_iban, bank_swift, default_currency, is_active }
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
requirePermission($user, 'manage_vendors');
$input = jsonInput();

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Vendor id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM vendors WHERE id = ?");
$stmt->execute([$id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Vendor not found']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE vendors SET
        name = ?, vendor_type = ?, contact_person = ?, phone = ?, email = ?,
        address = ?, vat_number = ?, bank_name = ?, bank_account_name = ?,
        bank_account_no = ?, bank_iban = ?, bank_swift = ?, default_currency = ?, is_active = ?
    WHERE id = ?
");
$stmt->execute([
    $input['name'] ?? '',
    $input['vendor_type'] ?? 'multi',
    $input['contact_person'] ?? null,
    $input['phone'] ?? null,
    $input['email'] ?? null,
    $input['address'] ?? null,
    $input['vat_number'] ?? null,
    $input['bank_name'] ?? null,
    $input['bank_account_name'] ?? null,
    $input['bank_account_no'] ?? null,
    $input['bank_iban'] ?? null,
    $input['bank_swift'] ?? null,
    $input['default_currency'] ?? 'SAR',
    $input['is_active'] ?? 1,
    $id,
]);

echo json_encode(['status' => 'success', 'message' => 'Vendor updated']);
