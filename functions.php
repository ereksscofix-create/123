<?php
// functions.php — общие вспомогательные функции

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Простая защита XSS в выводе
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Проверка входа
function checkLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Возвращает текущего пользователя из сессии
function currentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                error_log("currentUser: User ID " . $_SESSION['user_id'] . " not found in DB");
                return null;
            }
        } catch (PDOException $e) {
            error_log("currentUser DB error: " . $e->getMessage());
            return null;
        }
    }
    return $user;
}

// notifyUser: сохраняет уведомление в БД и отправляет email
function notifyUser(int $user_id, string $message, string $type = 'info', bool $sendEmail = false): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (:uid, :msg, :type)");
        $stmt->execute(['uid' => $user_id, 'msg' => $message, 'type' => $type]);
    } catch (PDOException $e) {
        error_log("notifyUser DB error: " . $e->getMessage());
        return false;
    }

    if ($sendEmail) {
        try {
            $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $user_id]);
            $u = $stmt->fetch();
            if ($u && !empty($u['email'])) {
                $to = $u['email'];
                $subject = "EHPST — уведомление";
                $body = "<p>Здравствуйте, " . e($u['name'] ?? '') . ".</p><div>" . e($message) . "</div>";
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=utf-8\r\n";
                $headers .= "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
                @mail($to, $subject, $body, $headers);
            }
        } catch (PDOException $e) {
            error_log("notifyUser email lookup error: " . $e->getMessage());
        }
    }
    return true;
}

// Пометить уведомление прочитанным
function markNotificationRead(int $id, int $user_id): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $id, 'uid' => $user_id]);
        return true;
    } catch (PDOException $e) {
        error_log("markNotificationRead error: " . $e->getMessage());
        return false;
    }
}
?>
