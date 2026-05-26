<?php
function log_activity($pdo, $action, $details = "") {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
    } catch (PDOException $e) {
        // Silently fail to not break the main flow
    }
}
?>