<?php
$host = 'localhost';
$dbname = 'test';
$username = 'root'; // XAMPP default
$password = '';     // XAMPP default

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage() . " <br><br><b>Please import database.sql into phpMyAdmin first!</b>");
}
?>