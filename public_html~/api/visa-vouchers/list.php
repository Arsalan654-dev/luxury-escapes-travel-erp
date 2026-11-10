<?php
// =====================================================
// GET /api/visa-vouchers/list.php?search=&client_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$clientId = $_GET['client_id'] ?? null;

$sql = "SELECT vv.*, c.name as client_name, c.client_code,
        vi.vendor_name, v.address as vendor_address, v.bank_name as vendor_bank_name,
        v.bank_account_name as vendor_bank_account_name, v.bank_account_no as vendor_bank_account_no,
        v.bank_iban as vendor_bank_iban, v.bank_swift as vendor_bank_swift
        FROM visa_client_vouchers vv
        JOIN clients c ON c.id = vv.client_id
        LEFT JOIN visa_invoices vi ON vi.id = vv.visa_invoice_id
        LEFT JOIN vendors v ON v.id = vi.vendor_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (vv.visa_type LIKE ? OR vv.guest_name LIKE ? OR vv.voucher_ref LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($clientId) {
    $sql .= " AND vv.client_id = ?";
    $params[] = $clientId;
}

$sql .= " ORDER BY vv.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
