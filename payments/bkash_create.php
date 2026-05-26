<?php
require_once '../config/database.php';
require_once '../config/bkash.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid Request");
}

$client_id = $_POST['client_id'];
$amount = $_POST['amount'];
$token = $_POST['token'];

// 1. Get bKash Authorization Token
function get_bkash_token() {
    $post_token = array(
        'app_key' => BKASH_APP_KEY,
        'app_secret' => BKASH_APP_SECRET
    );

    $url = BKASH_BASE_URL . "/tokenized/checkout/token/grant";
    $post_token = json_encode($post_token);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'password: ' . BKASH_PASSWORD,
        'username: ' . BKASH_USERNAME
    ));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_token);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL check if needed
    $result_data = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        die("CURL Error (Grant Token): " . $curl_error);
    }

    $response = json_decode($result_data, true);
    return $response['id_token'] ?? null;
}

$id_token = get_bkash_token();

if (!$id_token) {
    die("bKash Authentication Failed. Please check API credentials. Response: " . ($result_data ?? 'No response'));
}

// 2. Create Payment
$intent = "sale";
$proxy = array(
    'mode' => '0011',
    'payerReference' => 'ClientID_' . $client_id,
    'callbackURL' => BKASH_CALLBACK_URL . '?client_id=' . $client_id . '&token=' . $token,
    'amount' => $amount,
    'currency' => 'BDT',
    'intent' => $intent,
    'merchantInvoiceNumber' => 'INV_' . time()
);

$url = BKASH_BASE_URL . "/tokenized/checkout/create";
$post_data = json_encode($proxy);

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
curl_close($ch);

$response = json_decode($result_data, true);

if (isset($response['bkashURL'])) {
    // Save id_token in session or database temporarily if needed for execution
    session_start();
    $_SESSION['bkash_id_token'] = $id_token;
    
    header("Location: " . $response['bkashURL']);
    exit;
} else {
    echo "bKash Payment Creation Failed: " . ($response['statusMessage'] ?? 'Unknown Error');
}
?>