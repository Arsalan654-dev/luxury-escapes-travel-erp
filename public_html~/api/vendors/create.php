<?php
// =====================================================
// POST /api/vendors/create.php
// Body: { name, vendor_type, contact_person, phone, email, address,
//         vat_number, bank_name, bank_account_name, bank_account_no,
//         bank_iban, bank_swift, default_currency,
//         also_create_client_account: true/false }
//
// If also_create_client_account is true, a matching row is created
// in `clients` and linked both ways — used when this vendor also
// buys from us for their own customers (sale transactions).
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

$name = trim($input['name'] ?? '');
if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Vendor name is required']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO vendors
        (name, vendor_type, contact_person, phone, email, address, vat_number,
         bank_name, bank_account_name, bank_account_no, bank_iban, bank_swift,
         default_currency, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $name,
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
    ]);
    $vendorId = $pdo->lastInsertId();

    $clientId = null;
    if (!empty($input['also_create_client_account'])) {
        $stmt = $pdo->query("SELECT client_code FROM clients WHERE client_code LIKE 'LE-%' ORDER BY CAST(SUBSTRING(client_code, 4) AS UNSIGNED) DESC LIMIT 1");
        $last = $stmt->fetch();
        $nextNum = $last ? ((int)substr($last['client_code'], 3) + 1) : 1;
        $clientCode = 'LE-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO clients (client_code, name, phone, email, address, default_currency, linked_vendor_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $clientCode,
            $name,
            $input['phone'] ?? null,
            $input['email'] ?? null,
            $input['address'] ?? null,
            $input['default_currency'] ?? 'SAR',
            $vendorId,
        ]);
        $clientId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE vendors SET linked_client_id = ? WHERE id = ?");
        $stmt->execute([$clientId, $vendorId]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Vendor created',
        'id' => $vendorId,
        'linked_client_id' => $clientId,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create vendor', 'details' => $e->getMessage()]);
}
