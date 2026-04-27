<?php
require_once '../config/database.php';

if (isset($_GET['delete']) && isset($_GET['invoice_id'])) {
    $id = $_GET['delete'];
    $inv_id = $_GET['invoice_id'];
    
    // Delete payment
    $pdo->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
    
    // Recalculate invoice status
    $stmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
    $stmt->execute([$inv_id]);
    $invoice = $stmt->fetch();
    
    $pay_stmt = $pdo->prepare("SELECT SUM(amount) as paid FROM payments WHERE invoice_id = ?");
    $pay_stmt->execute([$inv_id]);
    $total_paid = $pay_stmt->fetch()['paid'] ?: 0;
    
    $new_status = 'Unpaid';
    if ($total_paid >= $invoice['total_amount']) {
        $new_status = 'Paid';
    } elseif ($total_paid > 0) {
        $new_status = 'Partial';
    }
    
    $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?")->execute([$new_status, $inv_id]);
    
    header("Location: payment_history.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Payment History</h2>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
            $stmt = $pdo->query("
                SELECT p.*, i.invoice_number, c.name as client_name 
                FROM payments p 
                JOIN invoices i ON p.invoice_id = i.id 
                JOIN clients c ON i.client_id = c.id 
                ORDER BY p.payment_date DESC, p.id DESC
            ");
            while ($row = $stmt->fetch()):
            ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('M d, Y', strtotime($row['payment_date'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap font-medium text-blue-600">
                    <a href="../invoices/view_invoice.php?id=<?= $row['invoice_id'] ?>"><?= htmlspecialchars($row['invoice_number']) ?></a>
                </td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['client_name']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded bg-gray-100 text-gray-800">
                        <?= htmlspecialchars($row['payment_method']) ?>
                    </span>
                    <?php if($row['note']): ?>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($row['note']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap font-bold text-green-600">৳<?= number_format($row['amount'], 2) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="edit_payment.php?id=<?= $row['id'] ?>" class="text-yellow-600 hover:text-yellow-900 mr-3" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="payment_history.php?delete=<?= $row['id'] ?>&invoice_id=<?= $row['invoice_id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this payment record? It will update the invoice status automatically.');"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php require_once '../includes/footer.php'; ?>