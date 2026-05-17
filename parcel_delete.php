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
        // Сначала проверим, чья это посылка (защита от IDOR)
        $stmt = $pdo->prepare("SELECT sender_id, recipient_id FROM parcels WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $p = $stmt->fetch();

        if (!$p) die("Посылка не найдена.");

        if ($user['role'] === 'worker') {
            // ПОЛНОЕ УДАЛЕНИЕ (Hard delete)
            $stmt = $pdo->prepare("DELETE FROM parcel_status WHERE parcel_id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("DELETE FROM parcel_codes WHERE parcel_id = :id");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("DELETE FROM parcels WHERE id = :id");
            $stmt->execute(['id' => $id]);
        } else {
            // ЛОГИЧЕСКОЕ УДАЛЕНИЕ (Soft delete)
            if ((int)$p['sender_id'] === (int)$user['id']) {
                $stmt = $pdo->prepare("UPDATE parcels SET is_deleted_by_sender = 1 WHERE id = :id");
                $stmt->execute(['id' => $id]);
            } elseif ((int)$p['recipient_id'] === (int)$user['id']) {
                $stmt = $pdo->prepare("UPDATE parcels SET is_deleted_by_recipient = 1 WHERE id = :id");
                $stmt->execute(['id' => $id]);
            } else {
                die("У вас нет прав на удаление этой посылки.");
            }
        }

    } catch (PDOException $e) {
        error_log("parcel delete error: " . $e->getMessage());
        // Можно добавить вывод ошибки, если нужно
    }
}

header("Location: dashboard.php");
exit;
