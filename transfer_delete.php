<?php
// transfer_delete.php — Удаление денежного перевода (Только для работников)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM money_transfers WHERE id = :id");
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        error_log("transfer delete error: " . $e->getMessage());
    }
}

header("Location: transfer_list.php");
exit;
