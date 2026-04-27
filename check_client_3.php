<?php
require_once 'config/database.php';

$client_id = 3;

// Total Invoiced (Subtotal)
$stmt = $pdo->prepare("SELECT SUM(total_amount) as total, SUM(applied_credit) as credit FROM invoices WHERE client_id = ?");
$stmt->execute([$client_id]);
$invoice_data = $stmt->fetch();
$total_invoiced = $invoice_data['total'] ?: 0;
$total_credit = $invoice_data['credit'] ?: 0;

// Total Paid
$pay_stmt = $pdo->prepare("
    SELECT SUM(p.amount) as paid 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.client_id = ?
");
$pay_stmt->execute([$client_id]);
$total_paid = $pay_stmt->fetch()['paid'] ?: 0;

$balance_due = $total_invoiced - $total_credit - $total_paid;

echo "Client ID: $client_id\n";
echo "Total Invoiced (Subtotal): ৳" . number_format($total_invoiced, 2) . "\n";
echo "Total Credit Applied: ৳" . number_format($total_credit, 2) . "\n";
echo "Total Paid via Payments: ৳" . number_format($total_paid, 2) . "\n";
echo "Calculated Balance Due: ৳" . number_format($balance_due, 2) . "\n";

// List invoices for details
echo "\nInvoice Details:\n";
$inv_stmt = $pdo->prepare("SELECT id, invoice_number, total_amount, applied_credit, (SELECT SUM(amount) FROM payments WHERE invoice_id = invoices.id) as paid FROM invoices WHERE client_id = ?");
$inv_stmt->execute([$client_id]);
while($row = $inv_stmt->fetch()) {
    $p = $row['paid'] ?: 0;
    $d = $row['total_amount'] - $row['applied_credit'] - $p;
    echo "ID: {$row['id']} | #{$row['invoice_number']} | Total: {$row['total_amount']} | Credit: {$row['applied_credit']} | Paid: $p | Due: $d\n";
}
?>