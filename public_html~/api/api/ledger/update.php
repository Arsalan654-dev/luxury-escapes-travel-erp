<?php
// =====================================================
// POST /api/ledger/update.php  (admin, accountant only)
// Body: { id, narration, debit, credit, entry_date }
// Records an audit trail entry and recalculates the client's
// running balance after the edit.
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

$id = $input['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Ledger entry id is required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM ledger_entries WHERE id = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();

if (!$old) {
    http_response_code(404);
    echo json_encode(['error' => 'Ledger entry not found']);
    exit;
}

$newValues = [
    'entry_date' => $input['entry_date'] ?? $old['entry_date'],
    'narration' => $input['narration'] ?? $old['narration'],
    'debit' => $input['debit'] ?? $old['debit'],
    'credit' => $input['credit'] ?? $old['credit'],
];

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        UPDATE ledger_entries
        SET entry_date = ?, narration = ?, debit = ?, credit = ?, is_edited = 1, updated_by = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $newValues['entry_date'],
        $newValues['narration'],
        $newValues['debit'],
        $newValues['credit'],
        $user['id'],
        $id,
    ]);

    // Audit trail
    $stmt = $pdo->prepare("
        INSERT INTO ledger_entry_edits (ledger_entry_id, edited_by, old_values, new_values)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $id,
        $user['id'],
        json_encode([
            'entry_date' => $old['entry_date'],
            'narration' => $old['narration'],
            'debit' => $old['debit'],
            'credit' => $old['credit'],
        ]),
        json_encode($newValues),
    ]);

    recalcClientLedger($pdo, $old['client_id']);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Ledger entry updated']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update ledger entry', 'details' => $e->getMessage()]);
}
