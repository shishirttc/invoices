<?php
require_once '../config/database.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
    header("Location: list_invoices.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Invoices</h2>
    <a href="create_invoice.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        <i class="fas fa-plus mr-2"></i> Create Invoice
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
            $stmt = $pdo->query("
                SELECT i.*, c.name as client_name,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id) as cash_paid,
                (SELECT COALESCE(SUM(discount), 0) FROM payments WHERE invoice_id = i.id) as total_discount
                FROM invoices i 
                JOIN clients c ON i.client_id = c.id 
                ORDER BY i.id DESC
            ");
            while ($row = $stmt->fetch()):
                $total_settled = $row['cash_paid'] + $row['total_discount'] + $row['applied_credit'];
                $due_amount = $row['total_amount'] - $total_settled;
                
                // For display, we show only Cash Paid as requested
                $display_paid = $row['cash_paid'];
            ?>
            <tr>
                <td class="px-3 py-4 whitespace-nowrap font-medium text-blue-600">
                    <a href="view_invoice.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['invoice_number']) ?></a>
                </td>
                <td class="px-3 py-4 whitespace-nowrap"><?= htmlspecialchars($row['client_name']) ?></td>
                <td class="px-3 py-4 whitespace-nowrap"><?= (float)$row['quantity'] ?></td>
                <td class="px-3 py-4 whitespace-nowrap font-semibold">৳<?= number_format($row['total_amount'], 2) ?></td>
                <td class="px-3 py-4 whitespace-nowrap text-green-600 font-semibold">৳<?= number_format($display_paid, 2) ?></td>
                <td class="px-3 py-4 whitespace-nowrap text-red-500 font-semibold">৳<?= number_format($due_amount, 2) ?></td>
                <td class="px-3 py-4 whitespace-nowrap">
                    <?php if ($row['status'] == 'Paid'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                    <?php elseif ($row['status'] == 'Partial'): ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Partial</span>
                    <?php else: ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Unpaid</span>
                    <?php endif; ?>
                </td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <?php
                    $wa_phone_list = preg_replace('/[^0-9]/', '', $row['phone'] ?? ''); // Fallback for safety
                    // We need client phone in list_invoices query. Let's update the query first.
                    ?>
                    <a href="generate_pdf.php?id=<?= $row['id'] ?>" class="text-gray-600 hover:text-gray-900 mr-3" target="_blank" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                    <a href="view_invoice.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="View"><i class="fas fa-eye"></i></a>
                    <a href="edit_invoice.php?id=<?= $row['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="list_invoices.php?delete=<?= $row['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this invoice?');" title="Delete"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php require_once '../includes/footer.php'; ?>