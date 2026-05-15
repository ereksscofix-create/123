<?php
// functions.php — Общие вспомогательные функции
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Защита от XSS
if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// Проверка авторизации
function checkLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Получение текущего пользователя
function currentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache === null) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $cache = $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }
    return $cache;
}

// Уведомление пользователя
function notifyUser(int $user_id, string $message, string $type = 'info', bool $sendEmail = false): bool {
    global $pdo;
    try {
        // Убираем created_at, так как в некоторых схемах его нет
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (:uid, :msg, :type)");
        $stmt->execute(['uid' => $user_id, 'msg' => $message, 'type' => $type]);
    } catch (PDOException $e) {
        return false;
    }
    return true;
}

// Пометить как прочитанное
function markNotificationRead(int $id, int $user_id): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $id, 'uid' => $user_id]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
?>
