<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    header("Location: list_invoices.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: list_invoices.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $unit_amount = $_POST['unit_amount'];
    $quantity = $_POST['quantity'];
    $total_amount = $_POST['total_amount'];
    $applied_credit = $_POST['applied_credit'] ?: 0;
    $notes = $_POST['notes'];
    $invoice_date = $_POST['invoice_date'];
    $current_time = date('H:i:s', strtotime($invoice['created_at']));
    $created_at = $invoice_date . ' ' . $current_time;

    // Handle Credit Adjustment
    $old_credit = $invoice['applied_credit'];
    $credit_diff = $applied_credit - $old_credit;
    
    if ($credit_diff != 0) {
        // If new credit is higher, subtract more from balance. If lower, add back to balance.
        $upd_cl = $pdo->prepare("UPDATE clients SET balance = balance - ? WHERE id = ?");
        $upd_cl->execute([$credit_diff, $invoice['client_id']]);
    }

    // Recalculate Status
    // Need to check total payments already made
    $pay_stmt = $pdo->prepare("SELECT SUM(amount + discount) as total_settled FROM payments WHERE invoice_id = ?");
    $pay_stmt->execute([$id]);
    $total_settled = $pay_stmt->fetch()['total_settled'] ?: 0;
    
    $total_covered = $total_settled + $applied_credit;
    
    $status = 'Unpaid';
    if ($total_covered >= $total_amount) {
        $status = 'Paid';
    } elseif ($total_covered > 0) {
        $status = 'Partial';
    }

    $stmt = $pdo->prepare("UPDATE invoices SET unit_amount = ?, quantity = ?, total_amount = ?, applied_credit = ?, notes = ?, status = ?, created_at = ? WHERE id = ?");
    $stmt->execute([$unit_amount, $quantity, $total_amount, $applied_credit, $notes, $status, $created_at, $id]);
    
    // Fetch details for logging
    $log_stmt = $pdo->prepare("SELECT c.name as client_name, p.page_name FROM clients c JOIN services s ON c.id = s.client_id JOIN pages p ON s.page_id = p.id WHERE s.id = ?");
    $log_stmt->execute([$invoice['service_id']]);
    $log_data = $log_stmt->fetch();
    $client_name = $log_data['client_name'] ?? 'Unknown';
    $page_name = $log_data['page_name'] ?? 'Unknown';

    log_activity($pdo, "Edit Invoice", "Updated invoice #{$invoice['invoice_number']} for $client_name ($page_name). New Amount: $total_amount BDT");

    header("Location: view_invoice.php?id=" . $id);
    exit;
}

$client = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$client->execute([$invoice['client_id']]);
$client_data = $client->fetch();

$service = $pdo->prepare("SELECT s.*, p.page_name FROM services s JOIN pages p ON s.page_id = p.id WHERE s.id = ?");
$service->execute([$invoice['service_id']]);
$service_data = $service->fetch();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Edit Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h2>
</div>

<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Invoice Number</label>
                <input type="text" value="<?= $invoice['invoice_number'] ?>" disabled class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 bg-gray-100 leading-tight">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Invoice Date *</label>
                <input type="date" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d', strtotime($invoice['created_at'])) ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Client</label>
            <input type="text" value="<?= htmlspecialchars($client_data['name']) ?>" disabled class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 bg-gray-100 leading-tight">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Service</label>
            <input type="text" value="<?= htmlspecialchars($service_data['page_name'] . ' - ' . $service_data['service_type']) ?>" disabled class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 bg-gray-100 leading-tight">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Unit Price (৳)</label>
                <input type="number" step="0.01" id="unit_amount" name="unit_amount" value="<?= $invoice['unit_amount'] ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                <input type="number" step="0.01" id="quantity" name="quantity" value="<?= $invoice['quantity'] ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Service Total (৳)</label>
                <input type="number" step="0.01" id="total_amount" name="total_amount" value="<?= $invoice['total_amount'] ?>" required readonly class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 bg-gray-100 leading-tight">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                Apply Credit (৳) 
                <span id="avail_credit_badge" class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded cursor-pointer hover:bg-green-200" title="Click to apply all">Current Balance: ৳<span id="avail_bal_val"><?= number_format($client_data['balance'], 2, '.', '') ?></span></span>
            </label>
            <input type="number" step="0.01" id="applied_credit" name="applied_credit" value="<?= $invoice['applied_credit'] ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            <p class="text-xs text-gray-500 mt-1">* Changing this will automatically adjust client balance.</p>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Invoice Notes</label>
            <textarea name="notes" rows="2" placeholder="Any additional information..." class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white"><?= htmlspecialchars($invoice['notes']) ?></textarea>
        </div>

        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
            <div class="flex justify-between items-center font-bold text-blue-800 text-lg">
                <span>Final Payable:</span>
                <span>৳<span id="final_payable">0.00</span></span>
            </div>
        </div>

        <div class="flex items-center justify-end">
            <a href="view_invoice.php?id=<?= $id ?>" class="text-gray-600 hover:text-gray-800 mr-4 font-bold">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Update Invoice
            </button>
        </div>
    </form>
</div>

<script>
    const unitAmountInput = document.getElementById('unit_amount');
    const quantityInput = document.getElementById('quantity');
    const totalAmountInput = document.getElementById('total_amount');
    const appliedCreditInput = document.getElementById('applied_credit');
    const finalPayableSpan = document.getElementById('final_payable');
    const clientBalance = <?= $client_data['balance'] ?>;
    const oldCredit = <?= $invoice['applied_credit'] ?>;

    function calculateTotals() {
        const unit = parseFloat(unitAmountInput.value) || 0;
        const qty = parseFloat(quantityInput.value) || 0;
        const total = unit * qty;
        totalAmountInput.value = total.toFixed(2);

        const credit = parseFloat(appliedCreditInput.value) || 0;
        const final = total - credit;
        finalPayableSpan.textContent = final.toFixed(2);
    }

    unitAmountInput.addEventListener('input', calculateTotals);
    quantityInput.addEventListener('input', calculateTotals);
    
    appliedCreditInput.addEventListener('input', function() {
        const maxCredit = clientBalance + oldCredit; // Maximum credit they can use is their current balance + what they already used
        const serviceTotal = parseFloat(totalAmountInput.value) || 0;
        
        let value = parseFloat(this.value) || 0;
        if (value > maxCredit) value = maxCredit;
        if (value > serviceTotal) value = serviceTotal;
        
        this.value = value.toFixed(2);
        calculateTotals();
    });

    // Initial calculation
    calculateTotals();

    // Click to apply credit
    const availCreditBadge = document.getElementById('avail_credit_badge');
    availCreditBadge.addEventListener('click', function() {
        const balanceVal = parseFloat(document.getElementById('avail_bal_val').textContent) || 0;
        const maxCredit = balanceVal + oldCredit;
        const serviceTotal = parseFloat(totalAmountInput.value) || 0;
        appliedCreditInput.value = Math.min(maxCredit, serviceTotal).toFixed(2);
        calculateTotals();
    });
</script>

<?php require_once '../includes/footer.php'; ?>