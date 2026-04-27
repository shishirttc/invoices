<?php
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: payment_history.php");
    exit;
}

$id = $_GET['id'];

// Get payment details
$stmt = $pdo->prepare("SELECT p.*, i.invoice_number, i.total_amount, i.applied_credit, i.id as inv_id FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE p.id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    header("Location: payment_history.php");
    exit;
}

$invoice_id = $payment['inv_id'];

// Calculate current due excluding THIS payment
$pay_stmt = $pdo->prepare("SELECT SUM(amount + discount) as total_settled FROM payments WHERE invoice_id = ? AND id != ?");
$pay_stmt->execute([$invoice_id, $id]);
$other_settled = $pay_stmt->fetch()['total_settled'] ?: 0;
$payable_amount = $payment['total_amount'] - $payment['applied_credit'];
$current_due = $payable_amount - $other_settled;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $discount = $_POST['discount'] ?: 0;
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $note = $_POST['note'];

    // Update payment
    $stmt = $pdo->prepare("UPDATE payments SET amount = ?, discount = ?, payment_method = ?, payment_date = ?, note = ? WHERE id = ?");
    $stmt->execute([$amount, $discount, $payment_method, $payment_date, $note, $id]);
    
    // Recalculate invoice status
    $sum_stmt = $pdo->prepare("SELECT SUM(amount + discount) as total_settled FROM payments WHERE invoice_id = ?");
    $sum_stmt->execute([$invoice_id]);
    $new_total_settled = $sum_stmt->fetch()['total_settled'] ?: 0;

    $new_status = 'Unpaid';
    if ($new_total_settled >= $payable_amount) {
        $new_status = 'Paid';
    } elseif ($new_total_settled > 0) {
        $new_status = 'Partial';
    }
    
    $upd_stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $upd_stmt->execute([$new_status, $invoice_id]);

    header("Location: payment_history.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Edit Payment</h2>
</div>

<div class="bg-white shadow rounded-lg p-6 max-w-xl">
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
        <p class="text-gray-700"><strong>Invoice Number:</strong> <?= htmlspecialchars($payment['invoice_number']) ?></p>
        <p class="text-gray-700"><strong>Invoice Total:</strong> ৳<?= number_format($payment['total_amount'], 2) ?></p>
        <p class="text-gray-700"><strong>Adjusted Total (after credit):</strong> ৳<?= number_format($payable_amount, 2) ?></p>
        <p class="text-blue-600 font-bold"><strong>Current Due (excluding this payment):</strong> ৳<?= number_format($current_due, 2) ?></p>
    </div>

    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Discount (৳)</label>
                <input type="number" step="0.01" id="discount" name="discount" value="<?= $payment['discount'] ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Amount (৳) *</label>
                <input type="number" step="0.01" id="amount" name="amount" value="<?= $payment['amount'] ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Method *</label>
                <select name="payment_method" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                    <?php 
                    $methods = ['BKash', 'Nagad', 'Rocket', 'Bank', 'Cash'];
                    foreach($methods as $m):
                    ?>
                        <option value="<?= $m ?>" <?= ($payment['payment_method'] == $m) ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Payment Date *</label>
                <input type="date" name="payment_date" value="<?= $payment['payment_date'] ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2">Note / Transaction ID</label>
            <textarea name="note" rows="2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?= htmlspecialchars($payment['note']) ?></textarea>
        </div>

        <div class="flex items-center justify-end">
            <a href="payment_history.php" class="text-gray-600 hover:text-gray-800 mr-4 font-bold">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Update Payment
            </button>
        </div>
    </form>
</div>

<script>
    const currentDue = <?= $current_due ?>;
    const discountInput = document.getElementById('discount');
    const amountInput = document.getElementById('amount');

    discountInput.addEventListener('input', function() {
        const discount = parseFloat(this.value) || 0;
        let newPayable = currentDue - discount;
        if (newPayable < 0) newPayable = 0;
        amountInput.value = newPayable.toFixed(2);
    });
</script>

<?php require_once '../includes/footer.php'; ?>