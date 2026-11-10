<?php
// =====================================================
// POST /api/payments/create.php
// Body: { client_id, paid_to, payment_method, amount, currency_code,
//         exchange_rate, payment_date }
//
// If the payment currency differs from the client's ledger currency,
// exchange_rate converts it (e.g. paying PKR against a SAR ledger).
// converted_amount = amount * exchange_rate, credited to the ledger.
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
requirePermission($user, 'manage_ledger');
$input = jsonInput();

$required = ['client_id', 'amount', 'currency_code', 'proof_path'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['error' => $field === 'proof_path' ? 'A payment screenshot/proof is required' : "Field '$field' is required"]);
        exit;
    }
}

// Get the client's ledger currency
$stmt = $pdo->prepare("SELECT default_currency FROM clients WHERE id = ?");
$stmt->execute([$input['client_id']]);
$client = $stmt->fetch();
if (!$client) {
    http_response_code(404);
    echo json_encode(['error' => 'Client not found']);
    exit;
}

$paymentDate = $input['payment_date'] ?? date('Y-m-d');
$exchangeRate = $input['exchange_rate'] ?? null;
$amount = (float)$input['amount'];
// Exchange rate is expressed as "1 [ledger currency] = [rate] [paid currency]"
// e.g. 1 SAR = 77.5 PKR (matches the classic "@77.5" ledger notation).
// So converting a PKR payment to SAR means dividing by the rate.
$convertedAmount = $exchangeRate ? round($amount / $exchangeRate, 2) : $amount;

$pdo->beginTransaction();
try {
    $voucherNo = nextVoucherNumber($pdo, 'JV');

    $narration = 'ACCOUNT TITLE ' . ($input['paid_to'] ?? 'Client') .
        ($exchangeRate ? " {$amount}/- @{$exchangeRate}" : '');

    $ledgerId = addLedgerEntry($pdo, [
        'client_id' => $input['client_id'],
        'entry_date' => $paymentDate,
        'voucher_type' => 'JV',
        'voucher_no' => $voucherNo,
        'narration' => $narration,
        'debit' => 0,
        'credit' => $convertedAmount,
        'currency_code' => $client['default_currency'],
        'exchange_rate' => $exchangeRate,
        'created_by' => $user['id'],
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO payments
        (ledger_entry_id, client_id, paid_to, payment_method, amount, currency_code,
         exchange_rate, converted_amount, proof_path, payment_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ledgerId,
        $input['client_id'],
        $input['paid_to'] ?? null,
        $input['payment_method'] ?? 'bank_transfer',
        $amount,
        $input['currency_code'],
        $exchangeRate,
        $convertedAmount,
        $input['proof_path'],
        $paymentDate,
        $user['id'],
    ]);

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'Payment recorded and credited to ledger',
        'voucher_no' => $voucherNo,
        'converted_amount' => $convertedAmount,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record payment', 'details' => $e->getMessage()]);
}
