<?php
// =====================================================
// POST /api/vendor-payments/create.php
// Body: { vendor_id, paid_via, payment_method, amount, currency_code,
//         exchange_rate, payment_date }
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

$required = ['vendor_id', 'amount', 'currency_code', 'proof_path'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400);
        echo json_encode(['error' => $field === 'proof_path' ? 'A payment screenshot/proof is required' : "Field '$field' is required"]);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT default_currency FROM vendors WHERE id = ?");
$stmt->execute([$input['vendor_id']]);
$vendor = $stmt->fetch();
if (!$vendor) {
    http_response_code(404);
    echo json_encode(['error' => 'Vendor not found']);
    exit;
}

$paymentDate = $input['payment_date'] ?? date('Y-m-d');
$exchangeRate = $input['exchange_rate'] ?? null;
$amount = (float)$input['amount'];
$convertedAmount = $exchangeRate ? round($amount / $exchangeRate, 2) : $amount;

$pdo->beginTransaction();
try {
    $voucherNo = nextVoucherNumber($pdo, 'JV');
    $narration = 'PAID VIA ' . ($input['paid_via'] ?? 'bank') . ($exchangeRate ? " {$amount}/- @{$exchangeRate}" : '');

    $ledgerId = addVendorLedgerEntry($pdo, [
        'vendor_id' => $input['vendor_id'],
        'entry_date' => $paymentDate,
        'voucher_type' => 'JV',
        'voucher_no' => $voucherNo,
        'narration' => $narration,
        'debit' => $convertedAmount,
        'credit' => 0,
        'currency_code' => $vendor['default_currency'],
        'created_by' => $user['id'],
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO vendor_payments
        (ledger_entry_id, vendor_id, paid_via, payment_method, amount, currency_code,
         exchange_rate, converted_amount, proof_path, payment_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ledgerId,
        $input['vendor_id'],
        $input['paid_via'] ?? null,
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
    echo json_encode(['status' => 'success', 'message' => 'Payment recorded', 'voucher_no' => $voucherNo, 'converted_amount' => $convertedAmount]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record payment', 'details' => $e->getMessage()]);
}
