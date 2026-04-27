<?php
$host = 'sql100.ezyro.com';
$dbname = 'ezyro_41758256_invoice';
$username = 'ezyro_41758256';
$password = '7726e631f';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage() . " <br><br><b>Please import database.sql into phpMyAdmin first!</b>");
}
?>