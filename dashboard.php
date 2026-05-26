<?php
require_once 'config/database.php';

// Fetch summary stats
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_invoices = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();

// Fetch Pending Payments (Invoices with Unpaid or Partial status)
// Total minus applied credit minus sum of payments
$stmt = $pdo->query("
    SELECT SUM(total_amount - applied_credit - (
        SELECT COALESCE(SUM(amount), 0) 
        FROM payments 
        WHERE payments.invoice_id = invoices.id
    )) 
    FROM invoices 
    WHERE status != 'Paid'
");
$pending_payments = $stmt->fetchColumn();
$pending_payments = $pending_payments ?: 0;

// Fetch Paid Amount
$paid_amount = $pdo->query("SELECT SUM(amount) FROM payments")->fetchColumn();
$paid_amount = $paid_amount ?: 0;

// Fetch Monthly Income
$current_month = date('Y-m');
$monthly_income = $pdo->query("SELECT SUM(amount) FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$current_month'")->fetchColumn();
$monthly_income = $monthly_income ?: 0;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <h2 class="text-2xl font-bold text-gray-800">Dashboard</h2>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="flex items-center">
            <div class="p-2 rounded-full bg-blue-100 text-blue-500 mr-3">
                <i class="fas fa-users fa-lg"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs text-gray-500 mb-0.5 font-semibold truncate">Total Clients</p>
                <p class="text-lg font-bold text-gray-800"><?= $total_clients ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
        <div class="flex items-center">
            <div class="p-2 rounded-full bg-purple-100 text-purple-500 mr-3">
                <i class="fas fa-file-invoice fa-lg"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs text-gray-500 mb-0.5 font-semibold truncate">Total Invoices</p>
                <p class="text-lg font-bold text-gray-800"><?= $total_invoices ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <div class="flex items-center">
            <div class="p-2 rounded-full bg-yellow-100 text-yellow-500 mr-3">
                <i class="fas fa-clock fa-lg"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs text-gray-500 mb-0.5 font-semibold truncate">Pending Due</p>
                <p class="text-lg font-bold text-gray-800 whitespace-nowrap">৳<?= number_format($pending_payments, 0) ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="flex items-center">
            <div class="p-2 rounded-full bg-green-100 text-green-500 mr-3">
                <i class="fas fa-hand-holding-usd fa-lg"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs text-gray-500 mb-0.5 font-semibold truncate">Total Paid</p>
                <p class="text-lg font-bold text-gray-800 whitespace-nowrap">৳<?= number_format($paid_amount, 0) ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
        <div class="flex items-center">
            <div class="p-2 rounded-full bg-indigo-100 text-indigo-500 mr-3">
                <i class="fas fa-chart-line fa-lg"></i>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs text-gray-500 mb-0.5 font-semibold truncate">Monthly Income</p>
                <p class="text-lg font-bold text-gray-800 whitespace-nowrap">৳<?= number_format($monthly_income, 0) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white shadow rounded-lg p-6">
    <h3 class="text-xl font-bold text-gray-800 mb-4">Recent Invoices</h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php
                $stmt = $pdo->query("
                    SELECT i.*, c.name as client_name,
                           (i.total_amount - i.applied_credit - COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id = i.id), 0)) as due_amount
                    FROM invoices i 
                    JOIN clients c ON i.client_id = c.id 
                    ORDER BY i.created_at DESC 
                    LIMIT 5
                ");
                if ($stmt->rowCount() > 0):
                    while ($invoice = $stmt->fetch()):
                ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                        <a href="invoices/view_invoice.php?id=<?= $invoice['id'] ?>"><?= htmlspecialchars($invoice['invoice_number']) ?></a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($invoice['client_name']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">৳<?= number_format($invoice['total_amount'], 2) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?= $invoice['due_amount'] <= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        ৳<?= number_format($invoice['due_amount'], 2) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($invoice['status'] == 'Paid'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                        <?php elseif ($invoice['status'] == 'Partial'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Partial</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d F, Y', strtotime($invoice['created_at'])) ?></td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">No recent invoices found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>