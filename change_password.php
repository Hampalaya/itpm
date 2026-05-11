<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['error_msg'] = 'Fields cannot be empty.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error_msg'] = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error_msg'] = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        
        logAudit($pdo, 'update', 'users', $_SESSION['user_id'], "Changed password");
        $_SESSION['success_msg'] = 'Password changed successfully.';
    }

    // Go back to where they came from
    $referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header("Location: $referer");
    exit;
}
?>