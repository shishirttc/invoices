<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Filter logic
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$selected_month = isset($_GET['month']) ? $_GET['month'] : '';

// If a specific month is selected, override period to 'custom'
if (!empty($selected_month)) {
    $period = 'custom';
}

// Helper function for calculations
function get_sum($pdo, $table, $column, $date_column, $period, $custom_month = '') {
    $where = "";
    if ($period == 'weekly') {
        $where = "WHERE $date_column >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period == 'monthly') {
        $where = "WHERE DATE_FORMAT($date_column, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    } elseif ($period == 'yearly') {
        $where = "WHERE DATE_FORMAT($date_column, '%Y') = DATE_FORMAT(CURDATE(), '%Y')";
    } elseif ($period == 'custom' && !empty($custom_month)) {
        $where = "WHERE DATE_FORMAT($date_column, '%Y-%m') = " . $pdo->quote($custom_month);
    }

    $stmt = $pdo->query("SELECT SUM($column) FROM $table $where");
    return $stmt->fetchColumn() ?: 0;
}

// Get all available months for the filter dropdown
$months_query = "
    SELECT DISTINCT DATE_FORMAT(payment_date, '%Y-%m') as m FROM payments
    UNION
    SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m') as m FROM expenses
    ORDER BY m DESC
";
$available_months = $pdo->query($months_query)->fetchAll(PDO::FETCH_COLUMN);

// Income Stats (from payments)
$income_val = get_sum($pdo, 'payments', 'amount', 'payment_date', $period, $selected_month);

// Expense Stats
$expense_tk_val = get_sum($pdo, 'expenses', 'amount_tk', 'expense_date', $period, $selected_month);
$expense_usd_val = get_sum($pdo, 'expenses', 'amount_usd', 'expense_date', $period, $selected_month);

// Average Rate calculation
$avg_rate = ($expense_usd_val > 0) ? ($expense_tk_val / $expense_usd_val) : 0;

// Profit calculation
$profit_val = $income_val - $expense_tk_val;

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Income & Expense Dashboard</h2>
        <p class="text-sm text-gray-500">Viewing data for: 
            <span class="font-semibold uppercase text-blue-600">
                <?= ($period == 'custom') ? date('F, Y', strtotime($selected_month . '-01')) : $period ?>
            </span>
        </p>
    </div>
    
    <div class="flex flex-wrap gap-2 items-center">
        <!-- Month Filter Dropdown -->
        <form action="" method="GET" class="flex items-center gap-2 mr-2">
            <select name="month" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm bg-white shadow-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">-- Select Month --</option>
                <?php foreach ($available_months as $m): ?>
                    <option value="<?= $m ?>" <?= $selected_month == $m ? 'selected' : '' ?>>
                        <?= date('F, Y', strtotime($m . '-01')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($selected_month)): ?>
                <a href="dashboard.php" class="text-red-500 hover:text-red-700" title="Clear Month Filter">
                    <i class="fas fa-times-circle"></i>
                </a>
            <?php endif; ?>
        </form>

        <div class="bg-white rounded-lg shadow p-1 flex border h-10 items-center">
            <a href="?period=weekly" class="px-3 py-1 text-xs font-medium rounded <?= ($period == 'weekly') ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">Weekly</a>
            <a href="?period=monthly" class="px-3 py-1 text-xs font-medium rounded <?= ($period == 'monthly' && empty($selected_month)) ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">Monthly</a>
            <a href="?period=yearly" class="px-3 py-1 text-xs font-medium rounded <?= ($period == 'yearly') ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">Yearly</a>
            <a href="?period=all" class="px-3 py-1 text-xs font-medium rounded <?= ($period == 'all') ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100' ?>">All Time</a>
        </div>
        <a href="add_expense.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200 flex items-center shadow-sm h-10">
            <i class="fas fa-plus mr-2 text-xs"></i> Add Expense
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Income Summary -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-b-4 border-green-500">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-100 text-green-600 rounded-lg">
                    <i class="fas fa-hand-holding-usd fa-lg"></i>
                </div>
                <span class="text-base font-bold text-green-600 uppercase tracking-wider">
                    <?= ($period == 'custom') ? 'Selected' : $period ?> Income
                </span>
            </div>
            <div>
                <p class="text-3xl font-black text-gray-800">৳<?= number_format($income_val, 2) ?></p>
                <p class="text-xs text-gray-500 mt-1">Total revenue received</p>
            </div>
        </div>
    </div>

    <!-- Expense Summary -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-b-4 border-red-500">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-red-100 text-red-600 rounded-lg">
                    <i class="fas fa-file-invoice-dollar fa-lg"></i>
                </div>
                <span class="text-base font-bold text-red-600 uppercase tracking-wider">
                    <?= ($period == 'custom') ? 'Selected' : $period ?> Expense
                </span>
            </div>
            <div>
                <p class="text-3xl font-black text-gray-800">৳<?= number_format($expense_tk_val, 2) ?></p>
                <p class="text-xs text-gray-500 mt-1">$<?= number_format($expense_usd_val, 2) ?> in USD <?= $avg_rate > 0 ? '(Avg Rate: ৳' . number_format($avg_rate, 2) . ')' : '' ?></p>
            </div>
        </div>
    </div>

    <!-- Profit Summary -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-b-4 <?= $profit_val >= 0 ? 'border-blue-500' : 'border-orange-500' ?>">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 <?= $profit_val >= 0 ? 'bg-blue-100 text-blue-600' : 'bg-orange-100 text-orange-600' ?> rounded-lg">
                    <i class="fas fa-chart-line fa-lg"></i>
                </div>
                <span class="text-base font-bold <?= $profit_val >= 0 ? 'text-blue-600' : 'text-orange-600' ?> uppercase tracking-wider">
                    <?= ($period == 'custom') ? 'Selected' : $period ?> Profit
                </span>
            </div>
            <div>
                <p class="text-3xl font-black text-gray-800">৳<?= number_format($profit_val, 2) ?></p>
                <p class="text-xs text-gray-500 mt-1">Net earnings after expenses</p>
            </div>
        </div>
    </div>
</div>

<!-- Expense List -->
<div class="bg-white shadow-sm rounded-xl overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h3 class="font-bold text-gray-800 italic">Recent Expense Transactions</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">SL</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">USD ($)</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider text-center">Ex. Rate</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">TK (৳)</th>
                    <th class="px-6 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
                <?php
                $where_table = "";
                if ($period == 'weekly') {
                    $where_table = "WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                } elseif ($period == 'monthly') {
                    $where_table = "WHERE DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
                } elseif ($period == 'yearly') {
                    $where_table = "WHERE DATE_FORMAT(expense_date, '%Y') = DATE_FORMAT(CURDATE(), '%Y')";
                } elseif ($period == 'custom' && !empty($selected_month)) {
                    $where_table = "WHERE DATE_FORMAT(expense_date, '%Y-%m') = " . $pdo->quote($selected_month);
                }

                $stmt = $pdo->query("SELECT * FROM expenses $where_table ORDER BY expense_date DESC, id DESC");
                $serial = 1;
                if ($stmt->rowCount() > 0):
                    while ($expense = $stmt->fetch()):
                        $rate = ($expense['amount_usd'] > 0) ? ($expense['amount_tk'] / $expense['amount_usd']) : 0;
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400 font-mono"><?= str_pad($serial++, 2, "0", STR_PAD_LEFT) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= date('d M, Y', strtotime($expense['expense_date'])) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-medium"><?= htmlspecialchars($expense['description']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-semibold">$<?= number_format($expense['amount_usd'], 2) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-red-600">
                        <?= $rate > 0 ? '৳' . number_format($rate, 2) : '-' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold italic">৳<?= number_format($expense['amount_tk'], 2) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex justify-center gap-2">
                            <a href="edit_expense.php?id=<?= $expense['id'] ?>" class="text-blue-600 hover:text-blue-900 bg-blue-50 p-2 rounded-lg transition-colors">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete_expense.php?id=<?= $expense['id'] ?>" onclick="return confirm('Are you sure you want to delete this expense?')" class="text-red-600 hover:text-red-900 bg-red-50 p-2 rounded-lg transition-colors">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                <tr>
                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">
                        <i class="fas fa-folder-open fa-3x mb-3 block opacity-20"></i>
                        No expense records found for this period.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>