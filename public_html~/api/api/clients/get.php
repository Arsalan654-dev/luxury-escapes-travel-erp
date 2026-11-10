<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

$pdo = getDbConnection();
$user = requireAuth($pdo);

$id = $_GET['id'] ?? null;
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Client id is required']); exit; }

$stmt = $pdo->prepare("SELECT c.*, v.name as linked_vendor_name FROM clients c LEFT JOIN vendors v ON v.id = c.linked_vendor_id WHERE c.id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { http_response_code(404); echo json_encode(['error' => 'Client not found']); exit; }

$stmt = $pdo->prepare("SELECT COALESCE(SUM(debit) - SUM(credit), 0) as balance FROM ledger_entries WHERE client_id = ?");
$stmt->execute([$id]);
$client['current_balance'] = $stmt->fetch()['balance'];

// Hotel Invoices — LEFT JOIN so invoices without vendor still show
$stmt = $pdo->prepare("SELECT hi.*, v.name as vendor_name FROM hotel_invoices hi LEFT JOIN vendors v ON v.id = hi.vendor_id WHERE hi.client_id = ? ORDER BY hi.check_in DESC");
$stmt->execute([$id]);
$hotelInvoices = $stmt->fetchAll();

// Hotel Vouchers — pull vendor from linked hotel_invoice
$stmt = $pdo->prepare("
    SELECT hv.*, v.name as vendor_name
    FROM hotel_vouchers hv
    LEFT JOIN hotel_invoices hi ON hi.id = hv.hotel_invoice_id
    LEFT JOIN vendors v ON v.id = hi.vendor_id
    WHERE hv.client_id = ? ORDER BY hv.check_in DESC
");
$stmt->execute([$id]);
$hotelVouchers = $stmt->fetchAll();

// Transport — LEFT JOIN vendor
$stmt = $pdo->prepare("SELECT t.*, v.name as vendor_name FROM transport_vouchers t LEFT JOIN vendors v ON v.id = t.vendor_id WHERE t.client_id = ? ORDER BY t.pickup_date DESC");
$stmt->execute([$id]);
$transport = $stmt->fetchAll();

// Transport Client Vouchers — pull vendor from linked transport_voucher
$stmt = $pdo->prepare("
    SELECT tcv.*, v.name as vendor_name
    FROM transport_client_vouchers tcv
    LEFT JOIN transport_vouchers tv ON tv.id = tcv.transport_voucher_id
    LEFT JOIN vendors v ON v.id = tv.vendor_id
    WHERE tcv.client_id = ? ORDER BY tcv.pickup_date DESC
");
$stmt->execute([$id]);
$transportVouchers = $stmt->fetchAll();

// Visa Client Vouchers — pull vendor from linked visa_invoice
$stmt = $pdo->prepare("
    SELECT vcv.*, v.name as vendor_name
    FROM visa_client_vouchers vcv
    LEFT JOIN visa_invoices vi ON vi.id = vcv.visa_invoice_id
    LEFT JOIN vendors v ON v.id = vi.vendor_id
    WHERE vcv.client_id = ? ORDER BY vcv.issue_date DESC
");
$stmt->execute([$id]);
$visaVouchers = $stmt->fetchAll();

// Visa Invoices — LEFT JOIN vendor
$stmt = $pdo->prepare("SELECT vi.*, v.name as vendor_name FROM visa_invoices vi LEFT JOIN vendors v ON v.id = vi.vendor_id WHERE vi.client_id = ? ORDER BY vi.issue_date DESC");
$stmt->execute([$id]);
$visaInvoices = $stmt->fetchAll();

// Payments
$stmt = $pdo->prepare("SELECT * FROM payments WHERE client_id = ? ORDER BY payment_date DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

// Ledger (Shajrah) — JOIN vendor from linked invoices
$stmt = $pdo->prepare("
    SELECT le.*,
        COALESCE(hv.name, tv.name, vv.name) as vendor_name
    FROM ledger_entries le
    LEFT JOIN hotel_invoices hi ON hi.id = le.linked_hotel_invoice_id
    LEFT JOIN vendors hv ON hv.id = hi.vendor_id
    LEFT JOIN transport_vouchers tr ON tr.id = le.linked_transport_voucher_id
    LEFT JOIN vendors tv ON tv.id = tr.vendor_id
    LEFT JOIN visa_invoices vi ON vi.id = le.linked_visa_invoice_id
    LEFT JOIN vendors vv ON vv.id = vi.vendor_id
    WHERE le.client_id = ?
    ORDER BY le.entry_date ASC, le.id ASC
");
$stmt->execute([$id]);
$ledger = $stmt->fetchAll();

// Guests
$stmt = $pdo->prepare("SELECT * FROM guests WHERE client_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$guests = $stmt->fetchAll();

// Timeline — vendor name included
$timeline = [];
foreach ($hotelInvoices as $h) {
    $timeline[] = ['type'=>'hotel','transaction_type'=>$h['transaction_type'],'date'=>$h['check_in'],'title'=>$h['hotel_name'].' ('.$h['room_type'].')','vendor'=>$h['vendor_name'],'amount'=>$h['total_amount'],'currency'=>$h['currency_code'],'ref'=>$h['booking_ref'],'status'=>$h['booking_status']];
}
foreach ($transport as $t) {
    $timeline[] = ['type'=>'transport','transaction_type'=>$t['transaction_type'],'date'=>$t['pickup_date'],'title'=>$t['route'],'vendor'=>$t['vendor_name'],'amount'=>$t['amount'],'currency'=>$t['currency_code'],'ref'=>$t['voucher_no'],'status'=>$t['status']];
}
foreach ($visaInvoices as $vi) {
    $timeline[] = ['type'=>'visa','transaction_type'=>$vi['transaction_type'],'date'=>$vi['issue_date'],'title'=>$vi['visa_type'].' - '.$vi['guest_name'],'vendor'=>$vi['vendor_name'],'amount'=>$vi['amount'],'currency'=>$vi['currency_code'],'ref'=>$vi['voucher_no'],'status'=>'confirmed'];
}
foreach ($payments as $p) {
    $timeline[] = ['type'=>'payment','transaction_type'=>'payment','date'=>$p['payment_date'],'title'=>'Paid to '.$p['paid_to'],'vendor'=>null,'amount'=>$p['amount'],'currency'=>$p['currency_code'],'ref'=>null,'status'=>'completed'];
}
usort($timeline, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

echo json_encode([
    'status'=>'success','client'=>$client,'guests'=>$guests,'timeline'=>$timeline,
    'hotel_invoices'=>$hotelInvoices,'hotel_vouchers'=>$hotelVouchers,
    'transport_vouchers'=>$transportVouchers,'visa_vouchers'=>$visaVouchers,
    'transport'=>$transport,'visa_invoices'=>$visaInvoices,
    'payments'=>$payments,'ledger'=>$ledger,
]);
