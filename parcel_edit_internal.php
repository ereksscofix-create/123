<?php
// parcel_edit_internal.php — Внутренние операции склада (без комиссий и смены ПД)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Access denied");

$action = $_POST['action'] ?? '';
$parcel_id = (int)($_POST['parcel_id'] ?? 0);

if ($action === 'move_shelf' && $parcel_id > 0) {
    $new_shelf = (int)($_POST['new_shelf'] ?? 0);
    if ($new_shelf >= 1 && $new_shelf <= 6) {
        try {
            $stmt = $pdo->prepare("UPDATE parcels SET shelf = :s WHERE id = :id");
            $stmt->execute(['s' => $new_shelf, 'id' => $parcel_id]);

            $status = "Перемещена на полку №$new_shelf [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $parcel_id, 'txt' => $status]);

            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'pvz_dashboard.php'));
            exit;
        } catch (Exception $e) { die("DB Error: " . $e->getMessage()); }
    }
}

header("Location: pvz_dashboard.php");
exit;
