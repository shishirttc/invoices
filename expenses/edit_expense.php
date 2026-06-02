<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
$stmt->execute([$id]);
$expense = $stmt->fetch();

if (!$expense) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount_usd = $_POST['amount_usd'] ?: 0;
    $amount_tk = $_POST['amount_tk'] ?: 0;
    $expense_date = $_POST['expense_date'];
    $description = $_POST['description'];

    $stmt = $pdo->prepare("UPDATE expenses SET amount_usd = ?, amount_tk = ?, expense_date = ?, description = ? WHERE id = ?");
    if ($stmt->execute([$amount_usd, $amount_tk, $expense_date, $description, $id])) {
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Update Expense', ?)");
        $log_stmt->execute([$_SESSION['user_id'], "Updated expense ID $id: $description ($amount_usd USD / $amount_tk TK)"]);
        
        header("Location: dashboard.php?updated=1");
        exit;
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">Edit Expense</h2>
        <a href="dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form action="" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="expense_date" value="<?= $expense['expense_date'] ?>" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" value="<?= htmlspecialchars($expense['description']) ?>" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (USD $)</label>
                    <input type="number" step="0.01" name="amount_usd" value="<?= $expense['amount_usd'] ?>" placeholder="0.00" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (TK ৳)</label>
                    <input type="number" step="0.01" name="amount_tk" value="<?= $expense['amount_tk'] ?>" placeholder="0.00" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-save mr-2"></i> Update Expense
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>