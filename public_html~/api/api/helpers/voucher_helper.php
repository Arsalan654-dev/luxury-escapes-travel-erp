<?php
// =====================================================
// Voucher numbering + ledger helpers
// =====================================================

// Generates the next sequential voucher number for a type in the current year
// e.g. nextVoucherNumber($pdo, 'FI') -> "FI-182"
function nextVoucherNumber(PDO $pdo, string $type): string {
    $year = date('Y');
    $startedHere = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedHere = true;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, last_number FROM voucher_sequences WHERE voucher_type = ? AND year = ? FOR UPDATE");
        $stmt->execute([$type, $year]);
        $row = $stmt->fetch();

        if ($row) {
            $next = $row['last_number'] + 1;
            $upd = $pdo->prepare("UPDATE voucher_sequences SET last_number = ? WHERE id = ?");
            $upd->execute([$next, $row['id']]);
        } else {
            $next = 1;
            $ins = $pdo->prepare("INSERT INTO voucher_sequences (voucher_type, year, last_number) VALUES (?, ?, 1)");
            $ins->execute([$type, $year]);
        }
        if ($startedHere) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($startedHere) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $type . '-' . $next;
}

// Recalculates running_balance for every ledger entry of a client,
// in chronological order. Call this after any insert/update/delete
// that touches a client's ledger.
function recalcClientLedger(PDO $pdo, int $clientId) {
    $stmt = $pdo->prepare("SELECT id, debit, credit FROM ledger_entries WHERE client_id = ? ORDER BY entry_date ASC, id ASC");
    $stmt->execute([$clientId]);
    $entries = $stmt->fetchAll();

    $balance = 0;
    $upd = $pdo->prepare("UPDATE ledger_entries SET running_balance = ? WHERE id = ?");
    foreach ($entries as $e) {
        $balance += (float)$e['debit'] - (float)$e['credit'];
        $upd->execute([$balance, $e['id']]);
    }
}

// ---- Vendor Ledger (Payables) helpers ----

function recalcVendorLedger(PDO $pdo, int $vendorId) {
    $stmt = $pdo->prepare("SELECT id, debit, credit FROM vendor_ledger_entries WHERE vendor_id = ? ORDER BY entry_date ASC, id ASC");
    $stmt->execute([$vendorId]);
    $entries = $stmt->fetchAll();

    $balance = 0;
    $upd = $pdo->prepare("UPDATE vendor_ledger_entries SET running_balance = ? WHERE id = ?");
    foreach ($entries as $e) {
        $balance += (float)$e['credit'] - (float)$e['debit'];
        $upd->execute([$balance, $e['id']]);
    }
}

// Inserts a vendor ledger entry and recalculates the vendor's running balance.
// credit = we now owe the vendor more (new purchase)
// debit  = we paid the vendor (reduces what we owe)
function addVendorLedgerEntry(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("
        INSERT INTO vendor_ledger_entries
        (vendor_id, entry_date, voucher_type, voucher_no, narration,
         linked_hotel_invoice_id, linked_transport_voucher_id, linked_visa_invoice_id,
         debit, credit, currency_code, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['vendor_id'],
        $data['entry_date'],
        $data['voucher_type'],
        $data['voucher_no'],
        $data['narration'],
        $data['linked_hotel_invoice_id'] ?? null,
        $data['linked_transport_voucher_id'] ?? null,
        $data['linked_visa_invoice_id'] ?? null,
        $data['debit'] ?? 0,
        $data['credit'] ?? 0,
        $data['currency_code'] ?? 'SAR',
        $data['created_by'] ?? null,
    ]);
    $id = $pdo->lastInsertId();
    recalcVendorLedger($pdo, $data['vendor_id']);
    return $id;
}

// Inserts a client ledger entry and recalculates the client's running balance
function addLedgerEntry(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (client_id, entry_date, voucher_type, voucher_no, reference, narration,
         linked_hotel_invoice_id, linked_transport_voucher_id, linked_visa_invoice_id,
         debit, credit, currency_code, exchange_rate, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['client_id'],
        $data['entry_date'],
        $data['voucher_type'],
        $data['voucher_no'],
        $data['reference'] ?? null,
        $data['narration'],
        $data['linked_hotel_invoice_id'] ?? null,
        $data['linked_transport_voucher_id'] ?? null,
        $data['linked_visa_invoice_id'] ?? null,
        $data['debit'] ?? 0,
        $data['credit'] ?? 0,
        $data['currency_code'] ?? 'SAR',
        $data['exchange_rate'] ?? null,
        $data['created_by'] ?? null,
    ]);
    $id = $pdo->lastInsertId();
    recalcClientLedger($pdo, $data['client_id']);
    return $id;
}
