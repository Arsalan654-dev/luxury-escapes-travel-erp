<?php
// =====================================================
// GET /api/visa/list.php?search=&client_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$clientId = $_GET['client_id'] ?? null;
$vendorId = $_GET['vendor_id'] ?? null;

$sql = "SELECT vi.*, c.name as client_name, v.name as vendor_name,
        v.address as vendor_address, v.bank_name as vendor_bank_name,
        v.bank_account_name as vendor_bank_account_name, v.bank_account_no as vendor_bank_account_no,
        v.bank_iban as vendor_bank_iban, v.bank_swift as vendor_bank_swift
        FROM visa_invoices vi
        JOIN clients c ON c.id = vi.client_id
        LEFT JOIN vendors v ON v.id = vi.vendor_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (vi.visa_type LIKE ? OR vi.guest_name LIKE ? OR vi.voucher_no LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($clientId) {
    $sql .= " AND vi.client_id = ?";
    $params[] = $clientId;
}
if ($vendorId) {
    $sql .= " AND vi.vendor_id = ?";
    $params[] = $vendorId;
}

$sql .= " ORDER BY vi.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
