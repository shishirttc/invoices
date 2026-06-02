<?php
require_once '../config/database.php';
require_once '../config/bkash.php';
require_once '../includes/functions.php';

session_start();

$paymentID = $_GET['paymentID'] ?? null;
$status = $_GET['status'] ?? null;
$client_id = $_GET['client_id'] ?? null;
$token = $_GET['token'] ?? null;
$id_token = $_SESSION['bkash_id_token'] ?? null;

if (!$paymentID || !$status || !$id_token) {
    die("Invalid Callback Data.");
}

if ($status === 'success') {
    // 3. Execute Payment
    $url = BKASH_BASE_URL . "/tokenized/checkout/execute";
    $post_data = json_encode(array('paymentID' => $paymentID));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: ' . $id_token,
        'X-APP-Key: ' . BKASH_APP_KEY
    ));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL check
    $result_data = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        header("Location: ../ledger/$token?payment=failed&msg=" . urlencode("CURL Error (Execute): " . $curl_error));
        exit;
    }

    $response = json_decode($result_data, true);

    // If already completed or transaction status is Completed, treat as success
    if ((isset($response['transactionStatus']) && $response['transactionStatus'] === 'Completed') || 
        (isset($response['statusMessage']) && strpos($response['statusMessage'], 'Already Completed') !== false)) {
        
        // If it's already completed and we don't have response fields like trxID in the current call, 
        // we should just redirect to the ledger success page.
        if (!isset($response['trxID']) && isset($response['statusMessage']) && strpos($response['statusMessage'], 'Already Completed') !== false) {
             header("Location: ../ledger/$token?payment=success&msg=already_done");
             exit;
        }

        $trxID = $response['trxID'];
        $amount = $response['amount'];
        $payment_date = date('Y-m-d');

        // 4. Record Payment in Database
        // We need to find which invoices to pay. Let's pay the oldest unpaid invoices first.
        $stmt = $pdo->prepare("
            SELECT i.id, i.total_amount, i.applied_credit,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id) as paid,
            (SELECT COALESCE(SUM(discount), 0) FROM payments WHERE invoice_id = i.id) as discount
            FROM invoices i
            WHERE i.client_id = ? AND i.status != 'Paid'
            ORDER BY i.created_at ASC
        ");
        $stmt->execute([$client_id]);
        $remaining_payment = $amount;

        while ($inv = $stmt->fetch()) {
            if ($remaining_payment <= 0) break;

            $due = $inv['total_amount'] - $inv['applied_credit'] - $inv['paid'] - $inv['discount'];
            if ($due <= 0) continue;

            $payment_for_this_invoice = min($remaining_payment, $due);
            
            // Insert into payments table
            $ins = $pdo->prepare("INSERT INTO payments (invoice_id, amount, payment_method, payment_date, note) VALUES (?, ?, 'bKash', ?, ?)");
            $ins->execute([$inv['id'], $payment_for_this_invoice, $payment_date, "bKash TrxID: $trxID"]);

            // Update invoice status
            $new_paid = $inv['paid'] + $payment_for_this_invoice;
            $new_status = ($new_paid >= ($inv['total_amount'] - $inv['applied_credit'] - $inv['discount'])) ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?")->execute([$new_status, $inv['id']]);

            $remaining_payment -= $payment_for_this_invoice;
        }

        // 5. If there is still remaining payment, add it to client's credit balance
        if ($remaining_payment > 0) {
            $upd_bal = $pdo->prepare("UPDATE clients SET balance = balance + ? WHERE id = ?");
            $upd_bal->execute([$remaining_payment, $client_id]);
        }

        // Fetch client name for logging
        $cl_stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
        $cl_stmt->execute([$client_id]);
        $client_name = $cl_stmt->fetchColumn() ?: "Unknown";

        // Log activity
        log_activity($pdo, "bKash Payment", "Successful bKash payment of $amount BDT for $client_name. TrxID: $trxID");

        // Redirect back to public ledger with success message
        header("Location: ../ledger/$token?payment=success&trxid=$trxID");
        exit;
    } else {
        header("Location: ../ledger/$token?payment=failed&msg=" . urlencode($response['statusMessage'] ?? 'Execution Failed'));
        exit;
    }
} else {
    header("Location: ../ledger/$token?payment=cancelled");
    exit;
}
?>