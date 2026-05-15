<?php
// parcel_delete.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // Удаляем связанные данные сначала (статусы и коды)
        $stmt = $pdo->prepare("DELETE FROM parcel_status WHERE parcel_id = :id");
        $stmt->execute(['id' => $id]);

        $stmt = $pdo->prepare("DELETE FROM parcel_codes WHERE parcel_id = :id");
        $stmt->execute(['id' => $id]);

        // Теперь удаляем саму посылку
        $stmt = $pdo->prepare("DELETE FROM parcels WHERE id = :id");
        $stmt->execute(['id' => $id]);

    } catch (PDOException $e) {
        error_log("parcel delete error: " . $e->getMessage());
        // Можно добавить вывод ошибки, если нужно
    }
}

header("Location: dashboard.php");
exit;
