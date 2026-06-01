<?php
// config.php
session_start();

// Database configuration
// Using SQLite because MySQL is not reachable in this environment
$db_file = __DIR__ . '/travel.db';
$dsn = "sqlite:$db_file";

try {
     $pdo = new PDO($dsn);
     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
     $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
     $pdo->exec("PRAGMA foreign_keys = ON;");
} catch (\PDOException $e) {
     error_log($e->getMessage());
     die("Database connection error.");
}

define('INVITE_CODE', 'HUSBANDS2024');
define('SITE_NAME', 'Our Travel Adventures');

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'] === 'kk' ? 'kk' : 'ru';
}
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ru';
}

$lang = $_SESSION['lang'];

function t($ru, $kk) {
    global $lang;
    return $lang === 'kk' ? $kk : $ru;
}

function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Global user variable if logged in
$u = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
}
?>
