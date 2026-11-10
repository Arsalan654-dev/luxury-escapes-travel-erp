<?php
// =====================================================
// GET /api/vendor-ledger/list.php?search=
// =====================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$search = trim($_GET['search'] ?? '');

$sql = "SELECT v.*,
        (SELECT COALESCE(SUM(credit) - SUM(debit), 0) FROM vendor_ledger_entries WHERE vendor_id = v.id) as payable_balance
        FROM vendors v
        WHERE v.is_active = 1";
$params = [];

if ($search) {
    $sql .= " AND v.name LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY v.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
