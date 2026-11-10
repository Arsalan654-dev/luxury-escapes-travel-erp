<?php
// =====================================================
// GET /api/transport-vouchers/list.php?search=&client_id=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$clientId = $_GET['client_id'] ?? null;

$sql = "SELECT tv.*, c.name as client_name, c.client_code,
        t.vendor_name, v.address as vendor_address, v.bank_name as vendor_bank_name,
        v.bank_account_name as vendor_bank_account_name, v.bank_account_no as vendor_bank_account_no,
        v.bank_iban as vendor_bank_iban, v.bank_swift as vendor_bank_swift
        FROM transport_client_vouchers tv
        JOIN clients c ON c.id = tv.client_id
        LEFT JOIN transport_vouchers t ON t.id = tv.transport_voucher_id
        LEFT JOIN vendors v ON v.id = t.vendor_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (tv.route LIKE ? OR tv.guest_name LIKE ? OR tv.voucher_ref LIKE ? OR c.name LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($clientId) {
    $sql .= " AND tv.client_id = ?";
    $params[] = $clientId;
}

$sql .= " ORDER BY tv.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
