<?php
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Authentication: Either logged in OR valid token provided
$is_authenticated = false;
session_start();
if (isset($_SESSION['user_id'])) {
    $is_authenticated = true;
} elseif (isset($_GET['token'])) {
    // Check if token is valid for the requested invoice
    $token = $_GET['token'];
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients c JOIN invoices i ON c.id = i.client_id WHERE i.id = ? AND c.ledger_token = ?");
    $stmt->execute([$id, $token]);
    if ($stmt->fetchColumn() > 0) {
        $is_authenticated = true;
    }
}

if (!$is_authenticated) {
    die("Access Denied: You must be logged in or provide a valid secure link to view this invoice.");
}

if (!isset($_GET['id'])) {
    die("Invoice ID not provided.");
}

$id = $_GET['id'];
$stmt = $pdo->prepare("
    SELECT i.*, c.name, c.company_name, c.address, c.phone, c.email,
           s.service_type, p.page_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    JOIN services s ON i.service_id = s.id
    JOIN pages p ON s.page_id = p.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY id ASC");
$pay_stmt->execute([$id]);
$payments = $pay_stmt->fetchAll();

$total_paid = 0;
$total_discount = 0;
foreach($payments as $p) {
    $total_paid += $p['amount'];
    $total_discount += ($p['discount'] ?? 0);
}
$due_amount = $invoice['total_amount'] - $invoice['applied_credit'] - $total_paid - $total_discount;

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice '.$invoice['invoice_number'].'</title>
    <style>
        body { font-family: "Helvetica", sans-serif; color: #333; font-size: 13px; line-height: 1.4; }
        .header { width: 100%; border-bottom: 2px solid #0052cc; padding-bottom: 10px; margin-bottom: 20px; }
        .header table { width: 100%; }
        .company-name { font-size: 22px; font-weight: bold; color: #0052cc; }
        .invoice-title { font-size: 20px; font-weight: bold; text-align: right; }
        .details { width: 100%; margin-bottom: 20px; }
        .details td { vertical-align: top; }
        .items, .payments { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th, .payments th { background-color: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6; }
        .items td, .payments td { padding: 10px; border-bottom: 1px solid #eee; }
        .totals { width: 45%; float: right; margin-bottom: 30px; }
        .totals table { width: 100%; }
        .totals td { padding: 5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .status { font-weight: bold; padding: 4px 8px; border-radius: 3px; }
        .paid { color: #155724; }
        .unpaid { color: #721c24; }
        .section-title { font-size: 16px; font-weight: bold; border-bottom: 1px solid #333; padding-bottom: 5px; margin-top: 30px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="company-name">Siddik IT Ltd.</div>
                    <div>Digital Marketing Agency</div>
                    <div>222, Kadirganj, Boalia, Rajshahi, Bangladesh</div>
                    <div style="font-weight: bold; margin-top: 5px;">Md. Salahuddin Shishir</div>
                    <div style="font-size: 11px;">Mobile: +8801758-330079 (WhatsApp)</div>
                    
                </td>
                <td class="text-right">
                    <div class="invoice-title">INVOICE</div>
                    <div># '.$invoice['invoice_number'].'</div>
                    <div>Date: '.date('d F, Y', strtotime($invoice['created_at'])).'</div>
                    <div style="margin-top: 8px;">
                        Status: <span class="status '.($invoice['status']=='Paid'?'paid':'unpaid').'">'.strtoupper($invoice['status']).'</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="details">
        <tr>
            <td style="width: 50%;">
                <div class="text-bold">Billed To:</div>
                <div style="font-size: 15px; font-weight: bold; margin-bottom: 2px;">'.$invoice['name'].'</div>
                <div style="font-size: 13px; font-weight: bold; color: #444; margin-bottom: 3px;">Page Name: '.$invoice['page_name'].'</div>
                <div>'.nl2br($invoice['address']).'</div>
                <div>'.$invoice['phone'].'</div>
                <div>'.$invoice['email'].'</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 45%;">Description</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="text-bold" style="font-size: 14px;">'.$invoice['service_type'].'</div>
                    <div style="font-size: 11px; color: #666; font-weight: bold; margin-top: 2px;">Page: '.$invoice['page_name'].'</div>
                </td>
                <td class="text-center">'.$invoice['quantity'].'</td>
                <td class="text-right">TK '.number_format($invoice['unit_amount'], 2).'</td>
                <td class="text-right">TK '.number_format($invoice['total_amount'], 2).'</td>
            </tr>
        </tbody>
    </table>

    '.($invoice['notes'] ? '
    <div style="margin-top: 10px; margin-bottom: 20px;">
        <div class="text-bold" style="margin-bottom: 5px;">Notes:</div>
        <div style="background-color: #fcfcfc; padding: 8px; border: 1px solid #eee; font-style: italic; color: #555;">
            '.nl2br(htmlspecialchars($invoice['notes'])).'
        </div>
    </div>
    ' : '').'

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">TK '.number_format($invoice['total_amount'], 2).'</td>
            </tr>';

            if ($invoice['applied_credit'] > 0) {
                $html .= '<tr>
                    <td style="color: blue; font-style: italic;">Adjustment (Previous Credit):</td>
                    <td class="text-right" style="color: blue;">- TK '.number_format($invoice['applied_credit'], 2).'</td>
                </tr>';
            }

            if ($total_discount > 0) {
                $html .= '<tr>
                    <td style="color: #ea580c; font-style: italic;">Total Discount:</td>
                    <td class="text-right" style="color: #ea580c;">- TK '.number_format($total_discount, 2).'</td>
                </tr>';
            }

            $html .= '<tr>
                <td>Paid Amount:</td>
                <td class="text-right" style="color: green;">- TK '.number_format($total_paid, 2).'</td>
            </tr>
            <tr>
                <td class="text-bold" style="border-top: 2px solid #000; padding-top: 8px; font-size: 15px;">Due Amount:</td>
                <td class="text-right text-bold" style="border-top: 2px solid #000; padding-top: 8px; color: red; font-size: 15px;">TK '.number_format($due_amount, 2).'</td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>';

    if (count($payments) > 0) {
        $html .= '
        <div class="section-title">Payment History</div>
        <table class="payments">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Note/TrxID</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>';
            foreach($payments as $p) {
                $html .= '
                <tr>
                    <td>'.date('d F, Y', strtotime($p['payment_date'])).'</td>
                    <td>'.$p['payment_method'].'</td>
                    <td style="color: #666; font-size: 11px;">'.$p['note'].'</td>
                    <td class="text-right text-bold">TK '.number_format($p['amount'], 2).'</td>
                </tr>';
                if ($p['discount'] > 0) {
                    $html .= '<tr>
                        <td colspan="3" class="text-right" style="font-size: 11px; color: #ea580c; border-bottom: none; padding-top: 0;">Discount applied to this payment:</td>
                        <td class="text-right" style="font-size: 11px; color: #ea580c; border-bottom: none; padding-top: 0;">- TK '.number_format($p['discount'], 2).'</td>
                    </tr>';
                }
            }
            $html .= '
            </tbody>
        </table>';
    }

    $html .= '
    <div style="margin-top: 50px; text-align: center; color: #999; font-size: 11px;">
        Thank you for your business! <br>
        This is a computer-generated invoice.
    </div>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Invoice_".$invoice['invoice_number'].".pdf", array("Attachment" => false));
?>