<?php
require_once 'config/database.php';

if (!isset($_GET['token'])) {
    die("Access Denied: Invalid Link.");
}

$token = $_GET['token'];

// Fetch Client Info by Token
$stmt = $pdo->prepare("SELECT * FROM clients WHERE ledger_token = ?");
$stmt->execute([$token]);
$client = $stmt->fetch();

if (!$client) {
    die("Access Denied: Invalid or Expired Link.");
}

$client_id = $client['id'];

// Financial Summary
$stmt = $pdo->prepare("
    SELECT 
        SUM(total_amount) as total_billed,
        SUM(applied_credit) as total_credit_applied
    FROM invoices 
    WHERE client_id = ?
");
$stmt->execute([$client_id]);
$invoice_stats = $stmt->fetch();
$total_billed = $invoice_stats['total_billed'] ?: 0;

// Total Paid
$pay_stmt = $pdo->prepare("
    SELECT SUM(p.amount) 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.client_id = ?
");
$pay_stmt->execute([$client_id]);
$total_paid = $pay_stmt->fetchColumn() ?: 0;

// Calculate Balance Due
$balance_due = 0;
$inv_stmt = $pdo->prepare("
    SELECT i.id, i.total_amount, i.applied_credit,
    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id) as paid
    FROM invoices i
    WHERE i.client_id = ?
");
$inv_stmt->execute([$client_id]);
while($row = $inv_stmt->fetch()) {
    $invoice_due = $row['total_amount'] - $row['applied_credit'] - $row['paid'];
    if ($invoice_due > 0) {
        $balance_due += $invoice_due;
    }
}

// Recent Invoices
$invoices = $pdo->prepare("SELECT * FROM invoices WHERE client_id = ? ORDER BY id DESC");
$invoices->execute([$client_id]);
$invoices = $invoices->fetchAll();

// Recent Payments
$payments = $pdo->prepare("
    SELECT p.*, i.invoice_number 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.client_id = ? 
    ORDER BY p.id DESC
");
$payments->execute([$client_id]);
$payments = $payments->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Ledger - <?= htmlspecialchars($client['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-4 md:p-10">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-blue-600">Siddik IT Ltd.</h1>
                <p class="text-gray-500">Client Statement / Ledger</p>
            </div>
            <div class="text-right hidden md:block">
                <p class="text-gray-600 font-bold"><?= date('M d, Y') ?></p>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6 mb-8 border-t-4 border-blue-600">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Welcome, <?= htmlspecialchars($client['name']) ?></h2>
            <p class="text-gray-600">This is your up-to-date financial statement with <strong>Siddik IT Ltd.</strong></p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <p class="text-sm text-gray-500 uppercase font-bold">Total Billed</p>
                <p class="text-2xl font-bold text-gray-800">৳<?= number_format($total_billed, 2) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <p class="text-sm text-gray-500 uppercase font-bold">Total Paid</p>
                <p class="text-2xl font-bold text-gray-800">৳<?= number_format($total_paid, 2) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                <p class="text-sm text-gray-500 uppercase font-bold">Balance Due</p>
                <p class="text-2xl font-bold text-red-600">৳<?= number_format($balance_due, 2) ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Invoices Section -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="font-bold text-gray-800">Invoices</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">Inv #</th>
                                <th class="px-6 py-3 text-left">Amount</th>
                                <th class="px-6 py-3 text-left">Status</th>
                                <th class="px-6 py-3 text-center">Action</th>
                                <th class="px-6 py-3 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <?php if(empty($invoices)): ?>
                                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No invoices found.</td></tr>
                            <?php else: ?>
                                <?php foreach($invoices as $inv): ?>
                                <tr>
                                    <td class="px-6 py-4 font-bold text-blue-600"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                    <td class="px-6 py-4">৳<?= number_format($inv['total_amount'], 2) ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $inv['status'] == 'Paid' ? 'bg-green-100 text-green-800' : ($inv['status'] == 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $inv['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="invoices/generate_pdf.php?id=<?= $inv['id'] ?>&token=<?= $token ?>" target="_blank" class="text-gray-600 hover:text-blue-600 font-bold" title="Download PDF">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-500"><?= date('M d, Y', strtotime($inv['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments Section -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="font-bold text-gray-800">Recent Payments</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-6 py-3 text-left">Date</th>
                                <th class="px-6 py-3 text-left">Method</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <?php if(empty($payments)): ?>
                                <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No payments found.</td></tr>
                            <?php else: ?>
                                <?php foreach($payments as $p): ?>
                                <tr>
                                    <td class="px-6 py-4"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($p['payment_method']) ?></div>
                                        <?php if($p['note']): ?>
                                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($p['note']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-green-600">৳<?= number_format($p['amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-10 text-center text-gray-500 text-sm">
            <p>&copy; <?= date('Y') ?> Siddik IT Ltd. All rights reserved.</p>
            <p>Rajshahi, Bangladesh | Md. Salahuddin Shishir</p>
        </div>
    </div>
</body>
</html>