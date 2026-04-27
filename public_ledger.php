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

// Get Filter Values
$filter_month = $_GET['month'] ?? ''; // Format: YYYY-MM

// Base query for stats
$summary_sql = "SELECT SUM(total_amount) as total_billed, SUM(applied_credit) as total_credit_applied FROM invoices WHERE client_id = ?";
$pay_summary_sql = "SELECT SUM(p.amount) FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.client_id = ?";

if ($filter_month) {
    $summary_sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $pay_summary_sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
}

// Financial Summary (Filtered or Total)
$stmt = $pdo->prepare($summary_sql);
if ($filter_month) {
    $stmt->execute([$client_id, $filter_month]);
} else {
    $stmt->execute([$client_id]);
}
$invoice_stats = $stmt->fetch();
$total_billed = $invoice_stats['total_billed'] ?: 0;

// Total Paid (Filtered or Total)
$pay_stmt = $pdo->prepare($pay_summary_sql);
if ($filter_month) {
    $pay_stmt->execute([$client_id, $filter_month]);
} else {
    $pay_stmt->execute([$client_id]);
}
$total_paid = $pay_stmt->fetchColumn() ?: 0;

// Calculate Balance Due (ALWAYS Lifetime for accuracy)
$balance_due = 0;
$inv_stmt = $pdo->prepare("
    SELECT i.id, i.total_amount, i.applied_credit,
    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id) as paid,
    (SELECT COALESCE(SUM(discount), 0) FROM payments WHERE invoice_id = i.id) as discount
    FROM invoices i
    WHERE i.client_id = ?
");
$inv_stmt->execute([$client_id]);
while($row = $inv_stmt->fetch()) {
    $invoice_due = $row['total_amount'] - $row['applied_credit'] - $row['paid'] - $row['discount'];
    if ($invoice_due > 0) {
        $balance_due += $invoice_due;
    }
}

// Recent Invoices (Filtered or Recent 20)
$inv_sql = "SELECT * FROM invoices WHERE client_id = ?";
if ($filter_month) {
    $inv_sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ? ORDER BY id DESC";
    $invoices_stmt = $pdo->prepare($inv_sql);
    $invoices_stmt->execute([$client_id, $filter_month]);
} else {
    $inv_sql .= " ORDER BY id DESC LIMIT 20";
    $invoices_stmt = $pdo->prepare($inv_sql);
    $invoices_stmt->execute([$client_id]);
}
$invoices = $invoices_stmt->fetchAll();

// Recent Payments (Filtered or Recent 20)
$pay_list_sql = "SELECT p.*, i.invoice_number FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.client_id = ?";
if ($filter_month) {
    $pay_list_sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ? ORDER BY p.id DESC";
    $payments_stmt = $pdo->prepare($pay_list_sql);
    $payments_stmt->execute([$client_id, $filter_month]);
} else {
    $pay_list_sql .= " ORDER BY p.id DESC LIMIT 20";
    $payments_stmt = $pdo->prepare($pay_list_sql);
    $payments_stmt->execute([$client_id]);
}
$payments = $payments_stmt->fetchAll();

// Get list of months for filter dropdown (from invoices and payments)
$months = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as m FROM invoices WHERE client_id = ?
    UNION
    SELECT DISTINCT DATE_FORMAT(payment_date, '%Y-%m') as m FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.client_id = ?
    ORDER BY m DESC
");
$months->execute([$client_id, $client_id]);
$available_months = $months->fetchAll(PDO::FETCH_COLUMN);
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
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-blue-600">Siddik IT Ltd.</h1>
                <p class="text-gray-500">Client Statement / Ledger</p>
            </div>
            <div class="w-full md:w-auto bg-white p-3 rounded-lg shadow-sm border flex flex-col sm:flex-row items-center gap-3">
                <label class="text-sm font-bold text-gray-600">Filter Month:</label>
                <form method="GET" class="flex gap-2 w-full sm:w-auto">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <select name="month" onchange="this.form.submit()" class="border rounded px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-full sm:w-auto bg-gray-50">
                        <option value="">Lifetime Statement</option>
                        <?php foreach($available_months as $m): ?>
                            <option value="<?= $m ?>" <?= ($filter_month == $m) ? 'selected' : '' ?>>
                                <?= date('F Y', strtotime($m . '-01')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if($filter_month): ?>
                        <a href="?token=<?= $token ?>" class="text-red-500 hover:text-red-700 p-1" title="Clear Filter">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6 mb-8 border-t-4 border-blue-600">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Welcome, <?= htmlspecialchars($client['name']) ?></h2>
            <p class="text-gray-600">This is your statement with <strong>Siddik IT Ltd.</strong> <?= $filter_month ? 'for <b>'.date('F Y', strtotime($filter_month.'-01')).'</b>' : '(Lifetime View)' ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <p class="text-sm text-gray-500 uppercase font-bold"><?= $filter_month ? 'Billed in '.date('M Y', strtotime($filter_month)) : 'Total Billed' ?></p>
                <p class="text-2xl font-bold text-gray-800">৳<?= number_format($total_billed, 2) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <p class="text-sm text-gray-500 uppercase font-bold"><?= $filter_month ? 'Paid in '.date('M Y', strtotime($filter_month)) : 'Total Paid' ?></p>
                <p class="text-2xl font-bold text-gray-800">৳<?= number_format($total_paid, 2) ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                <p class="text-sm text-gray-500 uppercase font-bold">Overall Balance Due</p>
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
                                <th class="px-2 py-3 text-left">Inv #</th>
                                <th class="px-2 py-3 text-left">Qty</th>
                                <th class="px-2 py-3 text-left">Amount</th>
                                <th class="px-2 py-3 text-left">Status</th>
                                <th class="px-6 py-3 text-center">Action</th>
                                <th class="px-6 py-3 text-right">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <?php if(empty($invoices)): ?>
                                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No invoices found.</td></tr>
                            <?php else: ?>
                                <?php foreach($invoices as $inv): ?>
                                <tr>
                                    <td class="px-6 py-4 font-bold text-blue-600"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                    <td class="px-2 py-4"><?= (float)$inv['quantity'] ?></td>
                                    <td class="px-2 py-4">৳<?= number_format($inv['total_amount'], 2) ?></td>
                                    <td class="px-2 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full <?= $inv['status'] == 'Paid' ? 'bg-green-100 text-green-800' : ($inv['status'] == 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $inv['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <a href="invoices/generate_pdf.php?id=<?= $inv['id'] ?>&token=<?= $token ?>" target="_blank" class="text-gray-600 hover:text-blue-600 font-bold" title="Download PDF">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-500"><?= date('d F, Y', strtotime($inv['created_at'])) ?></td>
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
                                <th class="px-2 py-3 text-left">Date</th>
                                <th class="px-2 py-3 text-left">Method</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <?php if(empty($payments)): ?>
                                <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No payments found.</td></tr>
                            <?php else: ?>
                                <?php foreach($payments as $p): ?>
                                <tr>
                                    <td class="px-2 py-4"><?= date('d F, Y', strtotime($p['payment_date'])) ?></td>
                                    <td class="px-2 py-4">
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