<?php
function logAudit($pdo, $action, $table, $recordId = null, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $table, $recordId, $description, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
}
?>