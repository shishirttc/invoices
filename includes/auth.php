<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Get the base path dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $dir = dirname($script_name);
    
    // If the directory is just a slash, we are at the root
    $base_path = ($dir == DIRECTORY_SEPARATOR || $dir == '/') ? '' : $dir;
    
    // Special handling: if we are inside a subfolder (like /clients), we need to go up one level
    if (in_array(basename($dir), ['clients', 'invoices', 'payments', 'services', 'pages'])) {
        $base_path = dirname($dir);
    }
    
    if (basename($_SERVER['PHP_SELF']) != 'login.php') {
        header("Location: " . $base_path . "/login.php");
        exit;
    }
}
?>