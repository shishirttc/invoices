<?php
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: list_invoices.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("
    SELECT i.*, c.name, c.company_name, c.address, c.phone, c.email,
           s.service_type, p.page_name, p.page_url
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    JOIN services s ON i.service_id = s.id
    JOIN pages p ON s.page_id = p.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: list_invoices.php");
    exit;
}

// Fetch payments and discounts
$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ?");
$pay_stmt->execute([$id]);
$payments = $pay_stmt->fetchAll();

$total_paid = 0;
$total_discount = 0;
foreach($payments as $p) {
    $total_paid += $p['amount'];
    $total_discount += ($p['discount'] ?? 0);
}
$due_amount = $invoice['total_amount'] - $invoice['applied_credit'] - $total_paid - $total_discount;

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?></h2>
    <div>
        <a href="edit_invoice.php?id=<?= $id ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded mr-2">
            <i class="fas fa-edit mr-1"></i> Edit Invoice
        </a>
        <a href="../payments/add_payment.php?invoice_id=<?= $id ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mr-2">
            <i class="fas fa-plus mr-1"></i> Add Payment
        </a>
        <a href="generate_pdf.php?id=<?= $id ?>" target="_blank" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2">
            <i class="fas fa-download mr-1"></i> Download PDF
        </a>
        <?php
        // Prepare WhatsApp Message
        $wa_phone = preg_replace('/[^0-9]/', '', $invoice['phone']);
        if (strlen($wa_phone) == 11 && substr($wa_phone, 0, 1) == '0') {
            $wa_phone = '88' . $wa_phone;
        }
        
        $message = "Hello " . $invoice['name'] . ",\n\n*Md. Salahuddin Shishir*\n+8801758330079\n\nThis is an invoice from *Siddik IT Ltd*.\n\n*Invoice #*: " . $invoice['invoice_number'] . "\n*Service*: " . $invoice['service_type'] . "\n*Total Amount*: ৳" . number_format($invoice['total_amount'], 2) . "\n*Credit Applied*: ৳" . number_format($invoice['applied_credit'], 2) . "\n*Discount*: ৳" . number_format($total_discount, 2) . "\n*Due Amount*: ৳" . number_format($due_amount, 2) . "\n\nঅনুগ্রহ করে যত দ্রুত সম্ভব পেমেন্টটি পরিশোধ করুন। ধন্যবাদ";
        $wa_url = "https://wa.me/" . $wa_phone . "?text=" . urlencode($message);
        ?>
        <a href="<?= $wa_url ?>" target="_blank" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
            <i class="fab fa-whatsapp mr-1"></i> Send WhatsApp
        </a>
    </div>
</div>

<div class="bg-white shadow rounded-lg p-8">
    <div class="flex justify-between border-b pb-6 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-600">Siddik IT Ltd.</h1>
            <p class="text-gray-500 mt-1">Digital Marketing Agency</p>
            <p class="text-gray-500">Rajshahi, Bangladesh</p>
            <p class="text-gray-600 font-medium mt-2">Md. Salahuddin Shishir</p>
            <p class="text-gray-500 text-sm">Mobile: +8801758-330079 (WhatsApp)</p>
        </div>
        <div class="text-right">
            <h3 class="text-xl font-bold text-gray-700">INVOICE</h3>
            <p class="text-gray-500">Number: <?= htmlspecialchars($invoice['invoice_number']) ?></p>
            <p class="text-gray-500">Date: <?= date('M d, Y', strtotime($invoice['created_at'])) ?></p>
            <div class="mt-2">
                <?php if ($invoice['status'] == 'Paid'): ?>
                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-800 font-bold">PAID</span>
                <?php elseif ($invoice['status'] == 'Partial'): ?>
                    <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 font-bold">PARTIAL</span>
                <?php else: ?>
                    <span class="px-3 py-1 rounded-full bg-red-100 text-red-800 font-bold">UNPAID</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex justify-between mb-8">
        <div>
            <h4 class="font-bold text-gray-700 mb-2">Invoice To:</h4>
            <p class="font-semibold text-gray-800"><?= htmlspecialchars($invoice['name']) ?></p>
            <?php if($invoice['company_name']) echo "<p class='text-gray-600'>".htmlspecialchars($invoice['company_name'])."</p>"; ?>
            <p class="text-gray-600"><?= nl2br(htmlspecialchars($invoice['address'])) ?></p>
            <p class="text-gray-600"><?= htmlspecialchars($invoice['phone']) ?></p>
            <p class="text-gray-600"><?= htmlspecialchars($invoice['email']) ?></p>
        </div>
    </div>

    <table class="w-full text-left border-collapse mb-8">
        <thead>
            <tr class="border-b-2 border-gray-300">
                <th class="py-3 font-semibold text-gray-700">Description</th>
                <th class="py-3 font-semibold text-gray-700 text-center">Qty</th>
                <th class="py-3 font-semibold text-gray-700 text-right">Unit Price</th>
                <th class="py-3 font-semibold text-gray-700 text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-gray-200">
                <td class="py-4">
                    <div class="font-bold text-gray-800"><?= htmlspecialchars($invoice['service_type']) ?></div>
                    <div class="text-sm text-gray-500">Page: <?= htmlspecialchars($invoice['page_name']) ?></div>
                    <?php if($invoice['page_url']): ?>
                        <div class="text-xs text-blue-500"><?= htmlspecialchars($invoice['page_url']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="py-4 text-center text-gray-800">
                    <?= $invoice['quantity'] ?>
                </td>
                <td class="py-4 text-right text-gray-800">
                    ৳<?= number_format($invoice['unit_amount'], 2) ?>
                </td>
                <td class="py-4 text-right font-semibold text-gray-800">
                    ৳<?= number_format($invoice['total_amount'], 2) ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if($invoice['notes']): ?>
    <div class="mb-8">
        <h4 class="font-bold text-gray-700 mb-2">Notes:</h4>
        <div class="bg-gray-50 p-4 rounded border border-gray-200 text-gray-700 italic">
            <?= nl2br(htmlspecialchars($invoice['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex justify-end">
        <div class="w-1/2">
            <div class="flex justify-between py-2">
                <span class="font-semibold text-gray-600">Subtotal:</span>
                <span class="text-gray-800">৳<?= number_format($invoice['total_amount'], 2) ?></span>
            </div>
            <?php if($invoice['applied_credit'] > 0): ?>
            <div class="flex justify-between py-2 text-blue-600">
                <span class="font-semibold italic">Adjustment (Previous Credit):</span>
                <span>- ৳<?= number_format($invoice['applied_credit'], 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if($total_discount > 0): ?>
            <div class="flex justify-between py-2 text-orange-600">
                <span class="font-semibold italic">Total Discount:</span>
                <span>- ৳<?= number_format($total_discount, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="flex justify-between py-2 text-green-600">
                <span class="font-semibold">Paid Amount:</span>
                <span>- ৳<?= number_format($total_paid, 2) ?></span>
            </div>
            <div class="flex justify-between py-3 border-t-2 border-gray-300">
                <span class="font-bold text-lg text-gray-800">Due Amount:</span>
                <span class="font-bold text-lg text-red-600">৳<?= number_format($due_amount, 2) ?></span>
            </div>
        </div>
    </div>

    <?php if(count($payments) > 0): ?>
    <div class="mt-10">
        <h4 class="font-bold text-gray-700 mb-4 border-b pb-2">Payment History</h4>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="text-gray-600">
                    <th class="py-2">Date</th>
                    <th class="py-2">Method</th>
                    <th class="py-2">Note</th>
                    <th class="py-2 text-right">Discount</th>
                    <th class="py-2 text-right">Paid Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-2"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                    <td class="py-2"><?= htmlspecialchars($p['payment_method']) ?></td>
                    <td class="py-2 text-gray-500"><?= htmlspecialchars($p['note']) ?></td>
                    <td class="py-2 text-right text-orange-600"><?= ($p['discount'] > 0) ? '৳'.number_format($p['discount'], 2) : '-' ?></td>
                    <td class="py-2 text-right font-semibold text-green-600">৳<?= number_format($p['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>