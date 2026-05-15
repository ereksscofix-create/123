<?php
// notifications.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$user_id = (int)$user['id'];

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'read' && $id > 0) {
    markNotificationRead($id, $user_id);
} elseif ($action === 'read_all') {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
    } catch (PDOException $e) {
        error_log("notifications read_all error: " . $e->getMessage());
    }
} elseif ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $id, 'uid' => $user_id]);
    } catch (PDOException $e) {
        error_log("notifications delete error: " . $e->getMessage());
    }
}

// Redirect back (preferer) or to dashboard
$back = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
// Avoid redirect loop if referer is notifications.php (though it shouldn't be normally used this way)
if (strpos($back, 'notifications.php') !== false) $back = 'dashboard.php';

header("Location: " . $back);
exit;
