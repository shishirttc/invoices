<?php
session_start();
session_destroy();
$base_path = '/invoices';
header("Location: " . $base_path . "/login.php");
exit;
?>