<?php
// =====================================================
// POST /api/clients/create.php
// Header: Authorization: Bearer <token>
// Body: { name, phone, email, cnic_passport, address, default_currency,
//         opening_balance, opening_balance_date, linked_vendor_id }
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
requirePermission($user, 'manage_clients');
$input = jsonInput();

$name = trim($input['name'] ?? '');
if (!$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Client name is required']);
    exit;
}

// Generate client code: LE-0001, LE-0002 ...
// Uses the highest existing numeric suffix (not COUNT) so it never
// collides after a client has been deleted.
$stmt = $pdo->query("SELECT client_code FROM clients WHERE client_code LIKE 'LE-%' ORDER BY CAST(SUBSTRING(client_code, 4) AS UNSIGNED) DESC LIMIT 1");
$last = $stmt->fetch();
$nextNum = $last ? ((int)substr($last['client_code'], 3) + 1) : 1;
$clientCode = 'LE-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare("
    INSERT INTO clients
    (client_code, name, phone, email, cnic_passport, address, default_currency,
     opening_balance, opening_balance_date, linked_vendor_id, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
");
$stmt->execute([
    $clientCode,
    $name,
    $input['phone'] ?? null,
    $input['email'] ?? null,
    $input['cnic_passport'] ?? null,
    $input['address'] ?? null,
    $input['default_currency'] ?? 'SAR',
    $input['opening_balance'] ?? 0,
    ($input['opening_balance_date'] ?? null) ?: null,
    $input['linked_vendor_id'] ?? null,
]);

$clientId = $pdo->lastInsertId();

// If there's an opening balance, log it as the first ledger entry (OB)
if (($input['opening_balance'] ?? 0) != 0) {
    $stmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (client_id, entry_date, voucher_type, voucher_no, narration, debit, credit, currency_code, running_balance, created_by)
        VALUES (?, ?, 'OB', ?, 'Opening Balance', ?, 0, ?, ?, ?)
    ");
    $ob = $input['opening_balance'];
    $stmt->execute([
        $clientId,
        $input['opening_balance_date'] ?? date('Y-m-d'),
        'OB-' . $clientCode,
        $ob > 0 ? $ob : 0,
        $input['default_currency'] ?? 'SAR',
        $ob,
        $user['id'],
    ]);
}

echo json_encode(['status' => 'success', 'message' => 'Client created', 'id' => $clientId, 'client_code' => $clientCode]);
