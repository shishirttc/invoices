<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Generate Invoice Number (using next AUTO_INCREMENT to prevent reuse of deleted IDs)
$stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoices' AND table_schema = DATABASE()");
$next_id = $stmt->fetchColumn() ?: 1;
$invoice_number = 'INV-' . date('Ymd') . '-' . sprintf('%04d', $next_id);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = $_POST['client_id'];
    $service_id = $_POST['service_id'];
    $unit_amount = $_POST['unit_amount'];
    $quantity = $_POST['quantity'];
    $total_amount = $_POST['total_amount']; // This is (unit_amount * quantity)
    $applied_credit = $_POST['applied_credit'] ?: 0;
    $notes = $_POST['notes'];
    $invoice_date = $_POST['invoice_date'];
    $current_time = date('H:i:s');
    $created_at = $invoice_date . ' ' . $current_time;

    // Use the invoice number from POST since it might have been updated by JS
    // Recalculate or make it enabled/hidden.
    // Better: Re-generate it based on the selected date to be safe.
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_name = 'invoices' AND table_schema = DATABASE()");
    $next_id = $stmt->fetchColumn() ?: 1;
    $invoice_number = 'INV-' . date('Ymd', strtotime($invoice_date)) . '-' . sprintf('%04d', $next_id);

    // Determine Status based on Applied Credit
    $status = 'Unpaid';
    if ($applied_credit >= $total_amount) {
        $status = 'Paid';
    } elseif ($applied_credit > 0) {
        $status = 'Partial';
    }

    // Deduct from client balance if credit used
    if ($applied_credit > 0) {
        $upd_cl = $pdo->prepare("UPDATE clients SET balance = balance - ? WHERE id = ?");
        $upd_cl->execute([$applied_credit, $client_id]);
    }

    $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, service_id, unit_amount, quantity, total_amount, applied_credit, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$invoice_number, $client_id, $service_id, $unit_amount, $quantity, $total_amount, $applied_credit, $notes, $status, $created_at]);
    $new_invoice_id = $pdo->lastInsertId();
    
    // Fetch names for logging
    $log_stmt = $pdo->prepare("SELECT c.name as client_name, p.page_name FROM clients c JOIN services s ON c.id = s.client_id JOIN pages p ON s.page_id = p.id WHERE s.id = ?");
    $log_stmt->execute([$service_id]);
    $log_data = $log_stmt->fetch();
    $client_name = $log_data['client_name'] ?? 'Unknown';
    $page_name = $log_data['page_name'] ?? 'Unknown';

    // Log activity
    log_activity($pdo, "Create Invoice", "Created invoice #$invoice_number for $client_name ($page_name). Amount: $total_amount BDT");
    
    header("Location: view_invoice.php?id=" . $new_invoice_id);
    exit;
}

$clients = $pdo->query("SELECT id, name, balance FROM clients ORDER BY name ASC")->fetchAll();
$clients_json = json_encode($clients);

$services = $pdo->query("
    SELECT s.id, s.client_id, s.service_type, s.total, p.page_name 
    FROM services s 
    JOIN pages p ON s.page_id = p.id 
")->fetchAll();
$services_json = json_encode($services);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Create Invoice</h2>
</div>

<div class="bg-white shadow rounded-lg p-6 max-w-2xl">
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Invoice Number</label>
                <input type="text" id="invoice_number_display" value="<?= $invoice_number ?>" disabled class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 bg-gray-100 leading-tight">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Invoice Date *</label>
                <input type="date" id="invoice_date" name="invoice_date" value="<?= date('Y-m-d') ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Client *</label>
            <select id="client_select" name="client_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                <option value="">-- Select Client --</option>
                <?php foreach($clients as $client): ?>
                    <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p id="balance_info" class="text-green-600 text-sm mt-1 font-bold hidden">Available Credit: ৳<span>0.00</span></p>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Service *</label>
            <select id="service_select" name="service_id" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white">
                <option value="">-- Select Client First --</option>
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Unit Price (৳)</label>
                <input type="number" step="0.01" id="unit_amount" name="unit_amount" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Quantity</label>
                <input type="number" step="0.01" id="quantity" name="quantity" value="" placeholder="0.00" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Service Total (৳)</label>
                <input type="number" step="0.01" id="total_amount" name="total_amount" required readonly class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 bg-gray-100 leading-tight">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                Apply Credit (৳) 
                <span id="avail_credit_badge" class="ml-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded hidden cursor-pointer hover:bg-green-200" title="Click to apply all">Available: ৳<span>0.00</span></span>
            </label>
            <input type="number" step="0.01" id="applied_credit" name="applied_credit" value="" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="0.00">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Invoice Notes</label>
            <textarea name="notes" rows="2" placeholder="Any additional information..." class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white"></textarea>
        </div>

        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
            <div class="flex justify-between items-center font-bold text-blue-800 text-lg">
                <span>Final Payable:</span>
                <span>৳<span id="final_payable">0.00</span></span>
            </div>
        </div>

        <div class="flex items-center justify-end">
            <a href="list_invoices.php" class="text-gray-600 hover:text-gray-800 mr-4 font-bold">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Generate Invoice
            </button>
        </div>
    </form>
</div>

<script>
    const allServices = <?= $services_json ?>;
    const allClients = <?= $clients_json ?>;
    const clientSelect = document.getElementById('client_select');
    const serviceSelect = document.getElementById('service_select');
    const unitAmountInput = document.getElementById('unit_amount');
    const quantityInput = document.getElementById('quantity');
    const totalAmountInput = document.getElementById('total_amount');
    const appliedCreditInput = document.getElementById('applied_credit');
    const finalPayableSpan = document.getElementById('final_payable');
    const balanceInfo = document.getElementById('balance_info');
    const availCreditBadge = document.getElementById('avail_credit_badge');

    function calculateTotals() {
        const unit = parseFloat(unitAmountInput.value) || 0;
        const qty = parseFloat(quantityInput.value) || 0;
        const total = unit * qty;
        totalAmountInput.value = total.toFixed(2);

        const credit = parseFloat(appliedCreditInput.value) || 0;
        const final = total - credit;
        finalPayableSpan.textContent = final.toFixed(2);
    }

    clientSelect.addEventListener('change', function() {
        const clientId = this.value;
        serviceSelect.innerHTML = '<option value="">-- Select Service --</option>';
        unitAmountInput.value = '0.00';
        quantityInput.value = '';
        appliedCreditInput.value = '';
        calculateTotals();
        
        if(clientId) {
            const client = allClients.find(c => c.id == clientId);
            if(client && parseFloat(client.balance) > 0) {
                const bal = parseFloat(client.balance).toFixed(2);
                balanceInfo.classList.remove('hidden');
                balanceInfo.querySelector('span').textContent = bal;
                
                availCreditBadge.classList.remove('hidden');
                availCreditBadge.querySelector('span').textContent = bal;
                
                appliedCreditInput.max = client.balance;
            } else {
                balanceInfo.classList.add('hidden');
                availCreditBadge.classList.add('hidden');
                appliedCreditInput.max = 0;
            }

            const filteredServices = allServices.filter(s => s.client_id == clientId);
            filteredServices.forEach(s => {
                const option = document.createElement('option');
                option.value = s.id;
                option.dataset.total = s.total;
                option.textContent = `${s.page_name} - ${s.service_type} (৳${s.total})`;
                serviceSelect.appendChild(option);
            });
        } else {
            balanceInfo.classList.add('hidden');
            availCreditBadge.classList.add('hidden');
        }
    });

    serviceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if(selectedOption && selectedOption.value !== "") {
            unitAmountInput.value = parseFloat(selectedOption.dataset.total).toFixed(2);
            quantityInput.value = '1';
            appliedCreditInput.value = '';
        } else {
            unitAmountInput.value = '0.00';
            quantityInput.value = '';
            appliedCreditInput.value = '';
        }
        calculateTotals();
    });

    unitAmountInput.addEventListener('input', calculateTotals);
    quantityInput.addEventListener('input', calculateTotals);

    appliedCreditInput.addEventListener('input', function() {
        if (this.value === '') {
            calculateTotals();
            return;
        }

        const clientId = clientSelect.value;
        const client = allClients.find(c => c.id == clientId);
        const maxCredit = client ? parseFloat(client.balance) : 0;
        const serviceTotal = parseFloat(totalAmountInput.value) || 0;

        let value = parseFloat(this.value) || 0;
        if (value > maxCredit) value = maxCredit;
        if (value > serviceTotal) value = serviceTotal;

        // Only format to fixed if the user is not actively typing or it exceeds limits
        if (parseFloat(this.value) > value) {
            this.value = value.toFixed(2);
        }
        
        calculateTotals();
    });

    // Update Invoice Number display when date changes
    const invoiceDateInput = document.getElementById('invoice_date');
    const invoiceNumberDisplay = document.getElementById('invoice_number_display');
    const nextId = '<?= sprintf('%04d', $next_id) ?>';

    invoiceDateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        if (!isNaN(selectedDate.getTime())) {
            const y = selectedDate.getFullYear();
            const m = String(selectedDate.getMonth() + 1).padStart(2, '0');
            const d = String(selectedDate.getDate()).padStart(2, '0');
            const dateStr = `${y}${m}${d}`;
            invoiceNumberDisplay.value = `INV-${dateStr}-${nextId}`;
        }
    });

    // Click to apply credit
    availCreditBadge.addEventListener('click', function() {
        const balance = parseFloat(this.querySelector('span').textContent) || 0;
        const serviceTotal = parseFloat(totalAmountInput.value) || 0;
        appliedCreditInput.value = Math.min(balance, serviceTotal).toFixed(2);
        calculateTotals();
    });
    </script>
<?php require_once '../includes/footer.php'; ?>