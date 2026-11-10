<?php
// =====================================================
// GET /api/clients/list.php?search=&is_active=1
// Header: Authorization: Bearer <token>
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');
$isActive = $_GET['is_active'] ?? null;

$sql = "SELECT c.*,
        (SELECT COALESCE(SUM(debit) - SUM(credit), 0) FROM ledger_entries WHERE client_id = c.id) as current_balance,
        v.name as linked_vendor_name
        FROM clients c
        LEFT JOIN vendors v ON v.id = c.linked_vendor_id
        WHERE 1=1";
$params = [];

// By default, hide client accounts that only exist because a vendor also buys
// from us (those belong in the Vendors screen). Pass include_vendor_accounts=1
// to see them here too.
if (empty($_GET['include_vendor_accounts'])) {
    $sql .= " AND c.linked_vendor_id IS NULL";
}

if ($search) {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.client_code LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($isActive !== null) {
    $sql .= " AND c.is_active = ?";
    $params[] = $isActive;
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

echo json_encode(['status' => 'success', 'data' => $clients]);
