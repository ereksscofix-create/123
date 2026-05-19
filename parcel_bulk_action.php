<?php
// parcel_bulk_action.php — Массовые операции над посылками
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Access denied");

$action = $_POST['action'] ?? '';
$ids = $_POST['parcel_ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    header("Location: pvz_dashboard.php");
    exit;
}

try {
    $ids_str = implode(',', array_map('intval', $ids));

    if ($action === 'move_shelf') {
        $new_shelf = (int)($_POST['new_shelf'] ?? 0);
        if ($new_shelf >= 1 && $new_shelf <= 6) {
            $stmt = $pdo->prepare("UPDATE parcels SET shelf = :s WHERE id IN ($ids_str)");
            $stmt->execute(['s' => $new_shelf]);

            $status = "Массовое перемещение на полку №$new_shelf [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        $stmt_s = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            foreach($ids as $id) {
            $stmt_s->execute(['pid' => $id, 'txt' => $status]);
            }
        }
    } elseif ($action === 'bulk_return') {
        $stmt = $pdo->prepare("UPDATE parcels SET is_return = 1, shelf = NULL WHERE id IN ($ids_str)");
        $stmt->execute();

        $status = "Массовый возврат [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        $stmt_s = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        foreach($ids as $id) {
            $stmt_s->execute(['pid' => $id, 'txt' => $status]);
        }
    } elseif ($action === 'bulk_delete') {
        $stmt = $pdo->prepare("DELETE FROM parcels WHERE id IN ($ids_str)");
        $stmt->execute();
    }

} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }

header("Location: pvz_dashboard.php");
exit;
