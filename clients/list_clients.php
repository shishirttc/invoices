<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Fetch details for logging
    $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $client_name = $stmt->fetchColumn();

    if ($client_name) {
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
        log_activity($pdo, "Delete Client", "Deleted client: $client_name");
    }

    header("Location: list_clients.php");
    exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl font-bold text-gray-800">Clients</h2>
    
    <div class="relative w-full md:w-96">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="clientSearch" placeholder="Search client name or company..." class="pl-10 pr-4 py-2 border rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>

    <a href="add_client.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded whitespace-nowrap">
        <i class="fas fa-plus mr-2"></i> Add Client
    </a>
</div>

<div class="bg-white shadow rounded-lg overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200" id="clientsTable">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due/Credit</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php
            $stmt = $pdo->query("SELECT * FROM clients ORDER BY id DESC");
            while ($row = $stmt->fetch()):
                // Calculate Balance Due exactly like view_ledger.php
                $balance_due = 0;
                $inv_stmt = $pdo->prepare("
                    SELECT i.total_amount, i.applied_credit,
                    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id) as paid,
                    (SELECT COALESCE(SUM(discount), 0) FROM payments WHERE invoice_id = i.id) as discount
                    FROM invoices i
                    WHERE i.client_id = ?
                ");
                $inv_stmt->execute([$row['id']]);
                while($inv = $inv_stmt->fetch()) {
                    $invoice_due = $inv['total_amount'] - $inv['applied_credit'] - $inv['paid'] - $inv['discount'];
                    if ($invoice_due > 0) {
                        $balance_due += $invoice_due;
                    }
                }
                
                $credit_balance = $row['balance'];
            ?>
            <tr class="client-row">
                <td class="px-6 py-4 whitespace-nowrap">
                    <a href="view_ledger.php?id=<?= $row['id'] ?>" class="text-blue-600 hover:text-blue-900 font-bold search-name">
                        <?= htmlspecialchars($row['name']) ?>
                    </a>
                </td>
                <td class="px-6 py-4 whitespace-nowrap search-company"><?= htmlspecialchars($row['company_name']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['phone']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex flex-col gap-1">
                        <?php if ($balance_due > 0): ?>
                            <div class="flex items-center gap-2">
                                <span class="text-black font-bold text-sm">৳<?= number_format($balance_due, 2) ?></span>
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-red-600 text-white">Due</span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($credit_balance > 0): ?>
                            <div class="flex items-center gap-2">
                                <span class="text-black font-bold text-sm">৳<?= number_format($credit_balance, 2) ?></span>
                                <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-green-600 text-white">Credit</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($balance_due <= 0 && $credit_balance <= 0): ?>
                            <span class="text-gray-400 text-sm">Clear</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="view_ledger.php?id=<?= $row['id'] ?>" class="text-green-600 hover:text-green-900 mr-3" title="View Ledger"><i class="fas fa-book"></i> Ledger</a>
                    <a href="edit_client.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                    <a href="list_clients.php?delete=<?= $row['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this client?');" title="Delete"><i class="fas fa-trash"></i> Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    document.getElementById('clientSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.client-row');
        
        rows.forEach(row => {
            const name = row.querySelector('.search-name').textContent.toLowerCase();
            const company = row.querySelector('.search-company').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || company.includes(searchTerm)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
</script>
<?php require_once '../includes/footer.php'; ?>