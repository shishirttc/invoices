<?php
require_once 'config/database.php';

if (!isset($_GET['token'])) {
    die("Access Denied: Invalid Link.");
}

$token = $_GET['token'];

// Fetch Client Info by Token
$stmt = $pdo->prepare("SELECT * FROM clients WHERE ledger_token = ?");
$stmt->execute([$token]);
$client = $stmt->fetch();

if (!$client) {
    die("Access Denied: Invalid or Expired Link.");
}

$client_id = $client['id'];

// Get Filter Values
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // Default to current month format: YYYY-MM

// Base query for stats
$summary_sql = "SELECT SUM(total_amount) as total_billed, SUM(applied_credit) as total_credit_applied FROM invoices WHERE client_id = ?";
$pay_summary_sql = "SELECT SUM(p.amount) FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.client_id = ?";

if ($filter_month) {
    $summary_sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    $pay_summary_sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
}

// Financial Summary (Filtered or Total)
$stmt = $pdo->prepare($summary_sql);
if ($filter_month) {
    $stmt->execute([$client_id, $filter_month]);
} else {
    $stmt->execute([$client_id]);
}
$invoice_stats = $stmt->fetch();
$total_billed = $invoice_stats['total_billed'] ?: 0;

// Total Paid (Filtered or Total)
$pay_stmt = $pdo->prepare($pay_summary_sql);
if ($filter_month) {
    $pay_stmt->execute([$client_id, $filter_month]);
} else {
    $pay_stmt->execute([$client_id]);
}
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
$inv_sql = "SELECT * FROM invoices WHERE client_id = ?";
if ($filter_month) {
    $inv_sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ? ORDER BY id DESC";
    $invoices_stmt = $pdo->prepare($inv_sql);
    $invoices_stmt->execute([$client_id, $filter_month]);
} else {
    $inv_sql .= " ORDER BY id DESC LIMIT 50";
    $invoices_stmt = $pdo->prepare($inv_sql);
    $invoices_stmt->execute([$client_id]);
}
$invoices = $invoices_stmt->fetchAll();

// Recent Payments
$pay_list_sql = "SELECT p.*, i.invoice_number FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.client_id = ?";
if ($filter_month) {
    $pay_list_sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ? ORDER BY p.id DESC";
    $payments_stmt = $pdo->prepare($pay_list_sql);
    $payments_stmt->execute([$client_id, $filter_month]);
} else {
    $pay_list_sql .= " ORDER BY p.id DESC LIMIT 50";
    $payments_stmt = $pdo->prepare($pay_list_sql);
    $payments_stmt->execute([$client_id]);
}
$payments = $payments_stmt->fetchAll();

// Available months for filter
$months = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as m FROM invoices WHERE client_id = ?
    UNION
    SELECT DISTINCT DATE_FORMAT(payment_date, '%Y-%m') as m FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.client_id = ?
    ORDER BY m DESC
");
$months->execute([$client_id, $client_id]);
$available_months = $months->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement - <?= htmlspecialchars($client['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .gradient-blue { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); }
        .gradient-red { background: linear-gradient(135deg, #be123c 0%, #fb7185 100%); }
        .gradient-green { background: linear-gradient(135deg, #15803d 0%, #4ade80 100%); }
        .card-shadow { box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05); }
        .bkash-btn { background-color: #e2136e; }
        .bkash-btn:hover { background-color: #c1105d; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen pb-12">
    <!-- Header/Nav -->
    <div class="bg-white border-b sticky top-0 z-40 px-4 md:px-8 py-4 card-shadow">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 gradient-blue rounded-xl flex items-center justify-center text-white shadow-lg">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-800 tracking-tight">Siddik IT Ltd.</h1>
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-widest">Client Portal</p>
                </div>
            </div>
            
            <form method="GET" class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl w-full md:w-auto">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <i class="fas fa-calendar-alt text-slate-400 ml-3"></i>
                <select name="month" onchange="this.form.submit()" class="bg-transparent border-none text-sm font-semibold text-slate-700 focus:ring-0 cursor-pointer pr-8">
                    <option value="">Lifetime Statement</option>
                    <?php foreach($available_months as $m): ?>
                        <option value="<?= $m ?>" <?= ($filter_month == $m) ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($m . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 pt-8">
        <?php if (isset($_GET['payment'])): ?>
            <?php if ($_GET['payment'] === 'success'): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 mb-8 rounded-2xl flex items-center gap-4 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <p class="font-bold">Payment Successful!</p>
                        <p class="text-sm opacity-90">Thank you. Your TrxID: <b><?= htmlspecialchars($_GET['trxid'] ?? '') ?></b>. Your account is updated.</p>
                    </div>
                </div>
            <?php elseif ($_GET['payment'] === 'failed'): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 mb-8 rounded-2xl flex items-center gap-4">
                    <div class="w-10 h-10 bg-rose-500 text-white rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-times"></i>
                    </div>
                    <div>
                        <p class="font-bold">Payment Failed</p>
                        <p class="text-sm opacity-90"><?= htmlspecialchars($_GET['msg'] ?? 'Transaction was not completed.') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Hero Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
            <div class="lg:col-span-2 gradient-blue rounded-[2rem] p-8 text-white shadow-2xl relative overflow-hidden flex flex-col justify-between min-h-[220px]">
                <div class="relative z-10">
                    <h2 class="text-3xl font-bold mb-2">Hello, <?= htmlspecialchars($client['name']) ?>!</h2>
                    <p class="text-blue-100 text-sm opacity-80"><?= $filter_month ? 'Statement for '.date('F Y', strtotime($filter_month)) : 'Viewing your lifetime statement' ?></p>
                </div>
                <div class="relative z-10 flex gap-4 mt-6">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 flex-1">
                        <p class="text-[10px] uppercase font-bold opacity-60 mb-1">Total Billed</p>
                        <p class="text-xl font-bold">৳<?= number_format($total_billed, 2) ?></p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 flex-1">
                        <p class="text-[10px] uppercase font-bold opacity-60 mb-1">Total Paid</p>
                        <p class="text-xl font-bold">৳<?= number_format($total_paid, 2) ?></p>
                    </div>
                </div>
                <!-- Abstract Decor -->
                <div class="absolute top-[-20%] right-[-10%] w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-[-10%] left-[-5%] w-32 h-32 bg-blue-400/20 rounded-full blur-2xl"></div>
            </div>

            <div class="bg-white rounded-[2rem] p-8 card-shadow flex flex-col justify-between border border-slate-100 relative overflow-hidden">
                <div>
                    <p class="text-slate-400 text-sm font-bold uppercase tracking-wider mb-1">Due Balance</p>
                    <h3 class="text-4xl font-black <?= $balance_due <= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                        ৳<?= number_format($balance_due, 2) ?>
                    </h3>
                </div>
                
                <?php if ($balance_due > 0): ?>
                    <button onclick="document.getElementById('bkashModal').classList.remove('hidden')" class="bkash-btn text-white w-full py-4 rounded-2xl font-bold shadow-lg shadow-pink-200 transition-all hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center gap-3 mt-6">
                        <img src="https://www.logo.wine/a/logo/BKash/BKash-Icon-Logo.wine.svg" class="w-6 h-6 bg-white rounded-full p-0.5" alt="bKash">
                        Pay with bKash
                    </button>
                <?php else: ?>
                    <div class="flex items-center gap-2 text-emerald-600 font-bold bg-emerald-50 p-3 rounded-xl mt-6 justify-center">
                        <i class="fas fa-check-circle"></i> No Dues
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Invoices -->
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-6 border-b flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-file-invoice text-blue-500"></i> Invoices
                    </h3>
                    <span class="text-[10px] font-bold bg-slate-200 text-slate-600 px-2 py-1 rounded-full uppercase">Recent 50</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b bg-slate-50/30">
                                <th class="px-8 py-4">Invoice Details</th>
                                <th class="px-4 py-4 text-center">USD</th>
                                <th class="px-4 py-4">Amount</th>
                                <th class="px-4 py-4 text-center">Status</th>
                                <th class="px-8 py-4 text-right">Download</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(empty($invoices)): ?>
                                <tr><td colspan="5" class="px-8 py-12 text-center text-slate-400 italic">No invoices found.</td></tr>
                            <?php else: ?>
                                <?php foreach($invoices as $inv): ?>
                                <tr class="hover:bg-slate-50 transition-colors group">
                                    <td class="px-8 py-5">
                                        <p class="font-bold text-slate-700 text-sm">#<?= htmlspecialchars($inv['invoice_number']) ?></p>
                                        <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d M, Y', strtotime($inv['created_at'])) ?></p>
                                    </td>
                                    <td class="px-4 py-5 text-center font-semibold text-slate-600 text-sm">$<?= number_format($inv['quantity'], 2) ?></td>
                                    <td class="px-4 py-5 font-semibold text-slate-800 text-sm">৳<?= number_format($inv['total_amount'], 2) ?></td>
                                    <td class="px-4 py-5 text-center">
                                        <?php 
                                            $s_color = $inv['status'] == 'Paid' ? 'bg-emerald-100 text-emerald-700' : ($inv['status'] == 'Partial' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
                                        ?>
                                        <span class="px-3 py-1 text-[10px] font-bold rounded-full uppercase <?= $s_color ?>">
                                            <?= $inv['status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-right flex justify-end gap-2">
                                        <a href="../invoices/generate_pdf.php?id=<?= $inv['id'] ?>&token=<?= $token ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-blue-600 hover:text-white transition-all inline-flex items-center justify-center" title="View">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="../invoices/generate_pdf.php?id=<?= $inv['id'] ?>&token=<?= $token ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-400 hover:bg-blue-600 hover:text-white transition-all inline-flex items-center justify-center" title="Download PDF">
                                            <i class="fas fa-download text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments -->
            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-6 border-b flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-history text-emerald-500"></i> Payment History
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b bg-slate-50/30">
                                <th class="px-8 py-4">Transaction Details</th>
                                <th class="px-4 py-4">Method</th>
                                <th class="px-8 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(empty($payments)): ?>
                                <tr><td colspan="3" class="px-8 py-12 text-center text-slate-400 italic">No payments found.</td></tr>
                            <?php else: ?>
                                <?php foreach($payments as $p): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-8 py-5">
                                        <p class="font-bold text-slate-700 text-sm">Inv #<?= htmlspecialchars($p['invoice_number']) ?></p>
                                        <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d M, Y', strtotime($p['payment_date'])) ?></p>
                                    </td>
                                    <td class="px-4 py-5">
                                        <div class="flex items-center gap-2">
                                            <?php if($p['payment_method'] == 'bKash'): ?>
                                                <span class="w-2 h-2 rounded-full bg-pink-500"></span>
                                            <?php else: ?>
                                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                            <?php endif; ?>
                                            <span class="text-xs font-medium text-slate-600"><?= $p['payment_method'] ?></span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 text-right font-bold text-emerald-600 text-sm">৳<?= number_format($p['amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-16 text-center">
            <div class="inline-block p-1 bg-white border border-slate-100 rounded-2xl shadow-sm mb-4">
                <div class="px-4 py-2 flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">System Secure & Live</p>
                </div>
            </div>
            <p class="text-slate-400 text-sm">&copy; <?= date('Y') ?> <span class="font-bold text-slate-600">Siddik IT Ltd.</span></p>
            <p class="text-slate-400 text-[10px] mt-1 italic">Crafted for Excellence | 222, Kadirganj, Boalia, Rajshahi, Bangladesh</p>
        </div>
    </div>

    <!-- bKash Modal -->
    <div id="bkashModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4 transition-all duration-300">
        <div class="relative bg-white rounded-[2.5rem] shadow-2xl max-w-md w-full overflow-hidden scale-in">
            <div class="bg-[#e2136e] p-10 text-white text-center relative overflow-hidden">
                <div class="w-20 h-16 bg-white rounded-2xl mx-auto mb-3 flex items-center justify-center shadow-lg relative z-10 p-2">
                    <img src="https://www.logo.wine/a/logo/BKash/BKash-Logo.wine.svg" class="w-full h-full" alt="bKash">
                </div>
                <p class="text-white/80 text-xs font-bold uppercase tracking-widest relative z-10">Secure Gateway</p>
                <!-- Decor -->
                <div class="absolute top-[-50%] right-[-50%] w-64 h-64 bg-white/10 rounded-full blur-3xl"></div>
            </div>
            <form action="payments/bkash_create.php" method="POST" class="p-10">
                <input type="hidden" name="client_id" value="<?= $client_id ?>">
                <input type="hidden" name="token" value="<?= $token ?>">
                
                <div class="mb-8">
                    <label class="block text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2 px-1">Amount to Pay (BDT)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-lg">৳</span>
                        <input type="number" step="0.01" name="amount" value="<?= $balance_due ?>" max="<?= $balance_due ?>" min="1" required class="w-full bg-slate-50 border-2 border-slate-100 rounded-2xl py-4 pl-10 pr-4 text-slate-800 font-black text-2xl focus:outline-none focus:border-[#e2136e] transition-all">
                    </div>
                </div>
                
                <div class="flex gap-4">
                    <button type="button" onclick="document.getElementById('bkashModal').classList.add('hidden')" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-4 rounded-2xl transition-all">Cancel</button>
                    <button type="submit" class="flex-[2] bg-[#e2136e] hover:bg-[#c1105d] text-white font-bold py-4 rounded-2xl shadow-xl shadow-pink-200 flex items-center justify-center gap-2 transition-all">
                        <i class="fas fa-lock text-sm"></i> Pay Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .scale-in { animation: scaleIn 0.3s ease-out forwards; }
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</body>
</html>