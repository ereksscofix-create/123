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

// CSRF защита
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
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

// API Обработка (Action Handler)
if (isset($_GET['action'])) {
    require_once __DIR__ . '/config.php';
    $user = currentUser();

    if ($_GET['action'] === 'get_issue_code' && isset($_GET['id'])) {
        if (!$user) { echo json_encode(['error' => 'auth']); exit; }

        $pid = (int)$_GET['id'];
        // Проверяем права (работник или получатель)
        $stmt = $pdo->prepare("SELECT recipient_id FROM parcels WHERE id = :id");
        $stmt->execute(['id' => $pid]);
        $parcel = $stmt->fetch();

        if (!$parcel || ($user['role'] !== 'worker' && (int)$parcel['recipient_id'] !== (int)$user['id'])) {
            echo json_encode(['error' => 'denied']); exit;
        }

        // Получаем существующий код на сегодня или создаем новый
        $stmt = $pdo->prepare("SELECT code FROM parcel_codes WHERE parcel_id = :pid AND code_date = DATE(NOW()) LIMIT 1");
        $stmt->execute(['pid' => $pid]);
        $code = $stmt->fetchColumn();

        if (!$code) {
            $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $secret = substr(md5(time() . $pid), 0, 6);
            $stmt = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :c, :s, DATE(NOW()))");
            $stmt->execute(['pid' => $pid, 'c' => $code, 's' => $secret]);
        }

        echo json_encode(['code' => $code]);
        exit;
    }
}
?>
