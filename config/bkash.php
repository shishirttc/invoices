<?php
// bKash API Credentials (Live)
define('BKASH_APP_KEY', 'cDlgjRy7ccKDVVhcYuyt38pstc');
define('BKASH_APP_SECRET', 'wXyl1v3NECgf9hJP3IfnlJ3jmKvM7kzSBTqY6cKqMcTv8SxoKXVv');
define('BKASH_USERNAME', '01819269273');
define('BKASH_PASSWORD', '4+4k3!cCO$4');
define('BKASH_BASE_URL', 'https://tokenized.pay.bka.sh/v1.2.0-beta'); 

// Callback URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_name = $_SERVER['SCRIPT_NAME']; // e.g., /test/payments/bkash_create.php
$dir = dirname($script_name); // e.g., /test/payments
define('BKASH_CALLBACK_URL', $protocol . '://' . $host . $dir . '/bkash_callback.php');
?>