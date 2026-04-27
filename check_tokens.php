<?php
require_once 'config/database.php';

echo "Checking Clients and Tokens:<br>";
try {
    $stmt = $pdo->query("SELECT id, name, ledger_token FROM clients");
    while ($row = $stmt->fetch()) {
        echo "ID: {$row['id']} | Name: {$row['name']} | Token: {$row['ledger_token']}<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>