<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $dir = dirname($script_name);
    
    // Go up one level if we are in a subfolder
    if (in_array(basename($dir), ['clients', 'invoices', 'payments', 'services', 'pages'])) {
        header("Location: ../login.php");
    } else {
        header("Location: login.php");
    }
    exit;
}
?>