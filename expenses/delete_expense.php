<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Get details for logging
    $stmt = $pdo->prepare("SELECT description FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    $description = $stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
    if ($stmt->execute([$id])) {
        // Log activity
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'Delete Expense', ?)");
        $log_stmt->execute([$_SESSION['user_id'], "Deleted expense ID $id: $description"]);
    }
}

header("Location: dashboard.php?deleted=1");
exit;
?>