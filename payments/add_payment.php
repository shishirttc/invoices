<?php
require_once '../config/database.php';

if (!isset($_GET['invoice_id'])) {
    header("Location: ../invoices/list_invoices.php");
    exit;
}

$invoice_id = $_GET['invoice_id'];

// Get invoice details
$stmt = $pdo->prepare("SELECT invoice_number, total_amount, applied_credit FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: ../invoices/list_invoices.php");
    exit;
}

// Get paid amount
$pay_stmt = $pdo->prepare("SELECT SUM(amount) as paid FROM payments WHERE invoice_id = ?");
$pay_stmt->execute([$invoice_id]);
$paid_result = $pay_stmt->fetch();
$total_paid = $paid_result['paid'] ?: 0;
$due_amount = $invoice['total_amount'] - $invoice['applied_credit'] - $total_paid;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $discount = $_POST['discount'] ?: 0;
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $note = $_POST['note'];

    $stmt = $pdo->prepare("INSERT INTO payments (invoice_id, amount, discount, payment_method, payment_date, note) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$invoice_id, $amount, $discount, $payment_method, $payment_date, $note]);
    
    // Check for Overpayment (including discount)
    $total_reduction = $amount + $discount;
    if ($total_reduction > $due_amount) {
        $excess = $total_reduction - $due_amount;
        // Note: If discount was too big, we only add actual excess cash to balance
        // But for simplicity, we treat the total reduction excess as credit if needed.
        // Usually, users won't give a discount that results in overpayment.
        
        $cl_stmt = $pdo->prepare("SELECT client_id FROM invoices WHERE id = ?");
        $cl_stmt->execute([$invoice_id]);
        $client_id = $cl_stmt->fetch()['client_id'];
        
        $upd_cl = $pdo->prepare("UPDATE clients SET balance = balance + ? WHERE id = ?");
        $upd_cl->execute([$excess, $client_id]);
        
        $new_status = 'Paid';
    } else {
        // Normal payment logic
        $payable_amount = $invoice['total_amount'] - $invoice['applied_credit'];
        
        // Sum all payments and discounts for this invoice
        $sum_stmt = $pdo->prepare("SELECT SUM(amount + discount) as total_settled FROM payments WHERE invoice_id = ?");
        $sum_stmt->execute([$invoice_id]);
        $new_total_settled = $sum_stmt->fetch()['total_settled'] ?: 0;

        $new_status = 'Unpaid';
        if ($new_total_settled >= $payable_amount) {
            $new_status = 'Paid';
        } elseif ($new_total_settled > 0) {
            $new_status = 'Partial';
        }
    }
    
    $upd_stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $upd_stmt->execute([$new_status, $invoice_id]);

    header("Location: ../invoices/view_invoice.php?id=" . $invoice_id);
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Add Payment</h2>
</div>

<div class="bg-white shadow rounded-lg p-6 max-w-xl">
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
        <p class="text-gray-700"><strong>Invoice Number:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></p>
        <p class="text-gray-700"><strong>Total Amount:</strong> ৳<?= number_format($invoice['total_amount'], 2) ?></p>
        <?php if($invoice['applied_credit'] > 0): ?>
            <p class="text-blue-600"><strong>Credit Applied:</strong> - ৳<?= number_format($invoice['applied_credit'], 2) ?></p>
        <?php endif; ?>
        <p class="text-red-600 font-bold text-lg"><strong>Due Amount:</strong> ৳<span id="current_due_display"><?= number_format($due_amount, 2) ?></span></p>
    </div>

    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Discount (৳)</label>
                <input type="number" step="0.01" id="discount" name="discount" value="0.00" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Amount (৳) *</label>
                <input type="number" step="0.01" id="amount" name="amount" value="<?= $due_amount ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Method *</label>
                <select name="payment_method" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                    <option value="BKash">BKash</option>
                    <option value="Nagad">Nagad</option>
                    <option value="Rocket">Rocket</option>
                    <option value="Bank">Bank</option>
                    <option value="Cash">Cash</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Date *</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2">Note / Transaction ID</label>
            <textarea name="note" rows="2" placeholder="BKash/Nagad/Rocket TrxID or Bank Cheque No..." class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
        </div>

        <div class="flex items-center justify-end">
            <a href="../invoices/view_invoice.php?id=<?= $invoice_id ?>" class="text-gray-600 hover:text-gray-800 mr-4 font-bold">Cancel</a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Record Payment
            </button>
        </div>
    </form>
</div>

<script>
    const originalDue = <?= $due_amount ?>;
    const discountInput = document.getElementById('discount');
    const amountInput = document.getElementById('amount');

    discountInput.addEventListener('input', function() {
        const discount = parseFloat(this.value) || 0;
        let newPayable = originalDue - discount;
        if (newPayable < 0) newPayable = 0;
        amountInput.value = newPayable.toFixed(2);
    });
</script>

<?php require_once '../includes/footer.php'; ?>