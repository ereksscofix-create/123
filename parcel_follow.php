<?php
// parcel_follow.php — Логика подписки на посылку
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$parcel_id = (int)($_GET['id'] ?? 0);
$q = $_GET['q'] ?? '';

if ($parcel_id > 0 && $user) {
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO parcel_followers (user_id, parcel_id) VALUES (:uid, :pid)");
        $stmt->execute(['uid' => $user['id'], 'pid' => $parcel_id]);
    } catch (PDOException $e) {
        // Ошибку можно залогировать, но пользователю лучше просто вернуться
    }
}

header("Location: track.php?q=" . urlencode($q));
exit;
