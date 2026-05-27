<?php
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: list_clients.php");
    exit;
}

$client_id = $_GET['id'];

// Get Filter Value
$filter = $_GET['filter'] ?? 'all'; // Default: all

$date_query_inv = "";
$date_query_pay = "";

switch ($filter) {
    case 'today':
        $date_query_inv = " AND DATE(created_at) = CURDATE()";
        $date_query_pay = " AND DATE(payment_date) = CURDATE()";
        break;
    case 'weekly':
        $date_query_inv = " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $date_query_pay = " AND YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'monthly':
        $date_query_inv = " AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $date_query_pay = " AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        break;
    case 'yearly':
        $date_query_inv = " AND YEAR(created_at) = YEAR(CURDATE())";
        $date_query_pay = " AND YEAR(payment_date) = YEAR(CURDATE())";
        break;
}

// Handle Credit Update
if (isset($_POST['update_balance'])) {
    $new_balance = $_POST['balance'];
    $stmt = $pdo->prepare("UPDATE clients SET balance = ? WHERE id = ?");
    $stmt->execute([$new_balance, $client_id]);
    header("Location: view_ledger.php?id=" . $client_id . "&filter=" . $filter);
    exit;
}

// Fetch Client Info
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header("Location: list_clients.php");
    exit;
}

// Financial Summary (Filtered)
$stmt = $pdo->prepare("
    SELECT 
        SUM(total_amount) as total_billed,
        SUM(applied_credit) as total_credit_applied
    FROM invoices 
    WHERE client_id = ? $date_query_inv
");
$stmt->execute([$client_id]);
$invoice_stats = $stmt->fetch();
$total_billed = $invoice_stats['total_billed'] ?: 0;

// Total Paid (Filtered)
$pay_stmt = $pdo->prepare("
    SELECT SUM(p.amount) 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.client_id = ? $date_query_pay
");
$pay_stmt->execute([$client_id]);
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

// Recent Invoices
$invoices = $pdo->prepare("SELECT * FROM invoices WHERE client_id = ? $date_query_inv ORDER BY id DESC");
$invoices->execute([$client_id]);
$invoices = $invoices->fetchAll();

// Recent Payments
$payments = $pdo->prepare("
    SELECT p.*, i.invoice_number 
    FROM payments p 
    JOIN invoices i ON p.invoice_id = i.id 
    WHERE i.client_id = ? $date_query_pay
    ORDER BY p.id DESC
");
$payments->execute([$client_id]);
$payments = $payments->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Prepare Public Link
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME'];
$dir = dirname($script_name);
$base_url = dirname($dir); // Go up one level from 'clients' folder
if ($base_url == '/' || $base_url == '\\') {
    $base_url = '';
}
$public_link = $protocol . "://" . $host . $base_url . "/ledger/" . $client['ledger_token'];
?>

<div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-6 gap-4">
    <div class="flex flex-col md:flex-row md:items-center gap-4 w-full xl:w-auto">
        <h2 class="text-2xl font-bold text-gray-800">Ledger: <?= htmlspecialchars($client['name']) ?></h2>
        
        <!-- Filter Dropdown -->
        <form method="GET" class="flex items-center gap-2 bg-white p-1 rounded-lg border shadow-sm">
            <input type="hidden" name="id" value="<?= $client_id ?>">
            <select name="filter" onchange="this.form.submit()" class="text-sm border-none focus:ring-0 bg-transparent font-semibold text-blue-600 cursor-pointer">
                <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Time Statement</option>
                <option value="today" <?= $filter == 'today' ? 'selected' : '' ?>>Today</option>
                <option value="weekly" <?= $filter == 'weekly' ? 'selected' : '' ?>>This Week</option>
                <option value="monthly" <?= $filter == 'monthly' ? 'selected' : '' ?>>This Month</option>
                <option value="yearly" <?= $filter == 'yearly' ? 'selected' : '' ?>>This Year</option>
            </select>
        </form>
    </div>

    <div class="flex flex-wrap gap-2 w-full xl:w-auto">
        <button onclick="copyToClipboard('<?= $public_link ?>')" class="flex-1 sm:flex-none justify-center bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded flex items-center gap-2" title="Share Ledger Link">
            <i class="fas fa-share-alt"></i> Ledger
        </button>
        <?php
        $wa_phone = preg_replace('/[^0-9]/', '', $client['phone']);
        if (strlen($wa_phone) == 11 && substr($wa_phone, 0, 1) == '0') {
            $wa_phone = '88' . $wa_phone;
        }
        $wa_message = "Hello " . $client['name'] . ",\n\n*Md. Salahuddin Shishir*\n+8801758330079\n\nThis is your current balance update from *Siddik IT Ltd*.\n\n*Total Billed*: ৳" . number_format($total_billed, 2) . "\n*Total Paid*: ৳" . number_format($total_paid, 2) . "\n*Current Due*: ৳" . number_format($balance_due, 2) . "\n\nঅনুগ্রহ করে যত দ্রুত সম্ভব পেমেন্টটি পরিশোধ করুন। ধন্যবাদ\n\nYou can also view your full statement here: " . $public_link;
        $wa_url = "https://wa.me/" . $wa_phone . "?text=" . urlencode($wa_message);
        ?>
        <a href="<?= $wa_url ?>" target="_blank" class="flex-1 sm:flex-none justify-center bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <button onclick="document.getElementById('creditModal').classList.remove('hidden')" class="flex-1 sm:flex-none justify-center bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <i class="fas fa-coins"></i> Credit
        </button>
        <a href="edit_client.php?id=<?= $client['id'] ?>" class="flex-1 sm:flex-none justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <i class="fas fa-edit"></i> Edit
        </a>
        <a href="list_clients.php" class="flex-1 sm:flex-none justify-center bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            Back
        </a>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Public Link copied to clipboard!");
    });
}
</script>

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
        <p class="text-2xl font-bold <?= $balance_due <= 0 ? 'text-green-600' : 'text-red-600' ?>">৳<?= number_format($balance_due, 2) ?></p>
    </div>
</div>

<!-- Current Credit Alert -->
<?php if($client['balance'] > 0): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8 rounded shadow-sm" role="alert">
    <p class="font-bold">Available Credit Balance</p>
    <p>This client has <span class="text-xl font-bold">৳<?= number_format($client['balance'], 2) ?></span> available credit that can be applied to future invoices.</p>
</div>
<?php endif; ?>

<!-- Credit Management Modal -->
<div id="creditModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Manage Client Credit</h3>
            <form method="POST" class="mt-4 text-left">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Current Credit Balance (৳)</label>
                    <input type="number" step="0.01" name="balance" value="<?= $client['balance'] ?>" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1 italic">Update this if the client has sent money in advance or has a refund.</p>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" onclick="document.getElementById('creditModal').classList.add('hidden')" class="bg-gray-500 text-white font-bold py-2 px-4 rounded">Cancel</button>
                    <button type="submit" name="update_balance" class="bg-blue-600 text-white font-bold py-2 px-4 rounded">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Client Info -->
    <div class="lg:col-span-1">
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Client Details</h3>
            <div class="space-y-3">
                <p><span class="font-bold text-gray-600">Company:</span> <?= htmlspecialchars($client['company_name'] ?: 'N/A') ?></p>
                <p><span class="font-bold text-gray-600">Email:</span> <?= htmlspecialchars($client['email'] ?: 'N/A') ?></p>
                <p><span class="font-bold text-gray-600">Phone:</span> <?= htmlspecialchars($client['phone'] ?: 'N/A') ?></p>
                <p><span class="font-bold text-gray-600">Address:</span> <?= nl2br(htmlspecialchars($client['address'] ?: 'N/A')) ?></p>
                <div class="pt-2">
                    <span class="font-bold text-gray-600">Notes:</span>
                    <p class="text-sm text-gray-500 italic"><?= nl2br(htmlspecialchars($client['notes'] ?: 'No notes available.')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Sections -->
    <div class="lg:col-span-2 space-y-8">
        <!-- Invoices Section -->
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Invoices</h3>
                <a href="../invoices/create_invoice.php?client_id=<?= $client_id ?>" class="text-blue-600 hover:text-blue-800 text-sm font-bold">+ Create Invoice</a>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">Inv #</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">USD</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach($invoices as $inv): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                            <td class="px-6 py-4 text-sm">$<?= number_format($inv['quantity'], 2) ?></td>
                            <td class="px-6 py-4 text-sm">৳<?= number_format($inv['total_amount'], 2) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full <?= $inv['status'] == 'Paid' ? 'bg-green-100 text-green-800' : ($inv['status'] == 'Partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= $inv['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="../invoices/view_invoice.php?id=<?= $inv['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View"><i class="fas fa-eye"></i></a>
                                <a href="../invoices/generate_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="text-gray-600 hover:text-gray-900" title="PDF"><i class="fas fa-file-pdf"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Payments Section -->
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="font-bold text-gray-800">Recent Payments</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">Inv #</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase">Method</th>
                        <th class="px-6 py-3 text-right font-medium text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(empty($payments)): ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No payments found.</td></tr>
                    <?php else: ?>
                        <?php foreach($payments as $p): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900"><?= date('d F, Y', strtotime($p['payment_date'])) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($p['invoice_number']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($p['payment_method']) ?></div>
                                <?php if($p['note']): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($p['note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-semibold text-green-600">৳<?= number_format($p['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>