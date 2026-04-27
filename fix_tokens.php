<?php
require_once 'config/database.php';

echo "Fixing Missing Tokens:<br>";
try {
    $stmt = $pdo->query("SELECT id, name FROM clients WHERE ledger_token IS NULL OR ledger_token = ''");
    $clients = $stmt->fetchAll();
    
    if (count($clients) === 0) {
        echo "No clients with missing tokens found.<br>";
    } else {
        foreach ($clients as $client) {
            $token = bin2hex(random_bytes(16));
            $update = $pdo->prepare("UPDATE clients SET ledger_token = ? WHERE id = ?");
            $update->execute([$token, $client['id']]);
            echo "Generated token for: {$client['name']} (ID: {$client['id']})<br>";
        }
        echo "Token fix completed!";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>