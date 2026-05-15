<?php
// parcel_delete.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$user_id = (int)$user['id'];
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // Проверяем права (в данном случае разрешено всем, как просил пользователь, но обычно проверяют роль или принадлежность)
        $stmt = $pdo->prepare("DELETE FROM parcels WHERE id = :id");
        $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        error_log("parcel delete error: " . $e->getMessage());
    }
}

header("Location: dashboard.php");
exit;
