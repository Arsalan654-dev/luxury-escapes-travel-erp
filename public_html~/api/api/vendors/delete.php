<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
$pdo = getDbConnection(); $user = requireAuth($pdo); requirePermission($user, 'manage_vendors'); $input = jsonInput();
$id = $input['id'] ?? null;
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Vendor id required']); exit; }
$stmt = $pdo->prepare("SELECT id, name FROM vendors WHERE id = ?"); $stmt->execute([$id]);
$vendor = $stmt->fetch();
if (!$vendor) { http_response_code(404); echo json_encode(['error' => 'Vendor not found']); exit; }

$pdo->beginTransaction();
try {
    // Nuclear approach: disable FK checks, clean everything, delete, re-enable
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Nullify all vendor references across all tables
    $nullify = [
        "UPDATE hotel_invoices SET vendor_id = NULL WHERE vendor_id = ?",
        "UPDATE transport_vouchers SET vendor_id = NULL WHERE vendor_id = ?",
        "UPDATE visa_invoices SET vendor_id = NULL WHERE vendor_id = ?",
    ];
    foreach ($nullify as $sql) { $pdo->prepare($sql)->execute([$id]); }

    // Safe nullify (tables may not exist)
    $safeNullify = [
        "UPDATE packages SET hotel_vendor_id = NULL WHERE hotel_vendor_id = ?",
        "UPDATE packages SET transport_vendor_id = NULL WHERE transport_vendor_id = ?",
        "UPDATE packages SET visa_vendor_id = NULL WHERE visa_vendor_id = ?",
        "UPDATE packages SET default_vendor_id = NULL WHERE default_vendor_id = ?",
        "UPDATE package_hotels SET hotel_vendor_id = NULL WHERE hotel_vendor_id = ?",
        "UPDATE package_transports SET transport_vendor_id = NULL WHERE transport_vendor_id = ?",
    ];
    foreach ($safeNullify as $sql) { try { $pdo->prepare($sql)->execute([$id]); } catch (\Exception $e) {} }

    // Delete vendor's own data
    $deletes = [
        "DELETE FROM vendor_payments WHERE ledger_entry_id IN (SELECT id FROM vendor_ledger_entries WHERE vendor_id = ?)",
        "DELETE FROM vendor_ledger_entries WHERE vendor_id = ?",
    ];
    foreach ($deletes as $sql) { try { $pdo->prepare($sql)->execute([$id]); } catch (\Exception $e) {} }

    // Unlink client account (don't delete it — it has its own history)
    try { $pdo->prepare("UPDATE clients SET linked_vendor_id = NULL WHERE linked_vendor_id = ?")->execute([$id]); } catch (\Exception $e) {}

    // Delete the vendor
    $pdo->prepare("DELETE FROM vendors WHERE id = ?")->execute([$id]);

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "Vendor '{$vendor['name']}' permanently deleted."]);
} catch (\Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete vendor', 'details' => $e->getMessage()]);
}
