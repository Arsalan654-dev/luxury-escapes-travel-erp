<?php
// =====================================================
// POST /api/vendor-ledger/create.php  (admin, accountant only)
// Body: { vendor_id, entry_date, narration, debit, credit, currency_code }
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

if (empty($input['vendor_id']) || empty($input['narration'])) {
    http_response_code(400);
    echo json_encode(['error' => 'vendor_id and narration are required']);
    exit;
}

$voucherNo = nextVoucherNumber($pdo, 'JV');

$id = addVendorLedgerEntry($pdo, [
    'vendor_id' => $input['vendor_id'],
    'entry_date' => $input['entry_date'] ?? date('Y-m-d'),
    'voucher_type' => 'JV',
    'voucher_no' => $voucherNo,
    'narration' => $input['narration'],
    'debit' => $input['debit'] ?? 0,
    'credit' => $input['credit'] ?? 0,
    'currency_code' => $input['currency_code'] ?? 'SAR',
    'created_by' => $user['id'],
]);

echo json_encode(['status' => 'success', 'message' => 'Ledger entry added', 'id' => $id, 'voucher_no' => $voucherNo]);
