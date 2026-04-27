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
<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold text-gray-800">Payment History</h2>
    
    <div class="relative w-full md:w-96">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="paymentSearch" placeholder="Search client, invoice # or method..." class="pl-10 pr-4 py-2 border rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>

<div class="bg-white shadow rounded-lg overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200" id="paymentsTable">
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
            <tr class="payment-row">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d F, Y', strtotime($row['payment_date'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap font-medium text-blue-600 search-inv">
                    <a href="../invoices/view_invoice.php?id=<?= $row['invoice_id'] ?>"><?= htmlspecialchars($row['invoice_number']) ?></a>
                </td>
                <td class="px-6 py-4 whitespace-nowrap search-client"><?= htmlspecialchars($row['client_name']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded bg-gray-100 text-gray-800 search-method">
                        <?= htmlspecialchars($row['payment_method']) ?>
                    </span>
                    <?php if($row['note']): ?>
                        <div class="text-xs text-gray-500 mt-1 search-note"><?= htmlspecialchars($row['note']) ?></div>
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

<script>
    document.getElementById('paymentSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.payment-row');
        
        rows.forEach(row => {
            const invNum = row.querySelector('.search-inv').textContent.toLowerCase();
            const clientName = row.querySelector('.search-client').textContent.toLowerCase();
            const method = row.querySelector('.search-method').textContent.toLowerCase();
            const noteElem = row.querySelector('.search-note');
            const note = noteElem ? noteElem.textContent.toLowerCase() : '';
            
            if (invNum.includes(searchTerm) || clientName.includes(searchTerm) || method.includes(searchTerm) || note.includes(searchTerm)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>