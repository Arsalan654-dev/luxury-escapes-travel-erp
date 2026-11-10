<?php
// =====================================================
// POST /api/visa/create.php
// Body: { vendor_id, client_id, guest_id, transaction_type,
//         visa_type, country, guest_name, issue_date, expiry_date,
//         amount, currency_code, notes }
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/voucher_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = getDbConnection();
$user = requireAuth($pdo);
requirePermission($user, 'manage_bookings');
$input = jsonInput();

$required = ['client_id', 'visa_type', 'guest_name', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

$pdo->beginTransaction();
try {
    $voucherNo = nextVoucherNumber($pdo, 'DN');
    $transactionType = $input['transaction_type'] ?? 'purchase';
    $issueDate = $input['issue_date'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO visa_invoices
        (vendor_id, client_id, guest_id, voucher_no, transaction_type, visa_type, country,
         guest_name, issue_date, expiry_date, amount, currency_code, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['vendor_id'] ?: null,
        $input['client_id'],
        $input['guest_id'] ?? null,
        $voucherNo,
        $transactionType,
        $input['visa_type'],
        $input['country'] ?? null,
        $input['guest_name'],
        $issueDate,
        $input['expiry_date'] ?? null,
        $input['amount'],
        $input['currency_code'] ?? 'PKR',
        $input['notes'] ?? null,
        $user['id'],
    ]);
    $visaId = $pdo->lastInsertId();

    $narration = $input['visa_type'] . ' - ' . $input['guest_name'];

    addLedgerEntry($pdo, [
        'client_id' => $input['client_id'],
        'entry_date' => $issueDate,
        'voucher_type' => 'DN',
        'voucher_no' => $voucherNo,
        'narration' => $narration,
        'linked_visa_invoice_id' => $visaId,
        'debit' => $input['amount'],
        'credit' => 0,
        'currency_code' => $input['currency_code'] ?? 'PKR',
        'created_by' => $user['id'],
    ]);

    if (!empty($input['vendor_id']) && $transactionType === 'purchase') {
        addVendorLedgerEntry($pdo, [
            'vendor_id' => $input['vendor_id'],
            'entry_date' => $issueDate,
            'voucher_type' => 'DN',
            'voucher_no' => $voucherNo,
            'narration' => $narration,
            'linked_visa_invoice_id' => $visaId,
            'credit' => $input['amount'],
            'debit' => 0,
            'currency_code' => $input['currency_code'] ?? 'PKR',
            'created_by' => $user['id'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Visa invoice created and posted to ledger',
        'id' => $visaId,
        'voucher_no' => $voucherNo,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create visa invoice', 'details' => $e->getMessage()]);
}
