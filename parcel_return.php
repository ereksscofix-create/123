<?php
// parcel_return.php — Оформление возврата оплаченной посылки
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') {
    die("Доступ только для сотрудников.");
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID посылки не указан.");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");

    // Логика возврата
    $refund_code = '';
    $refund_issued = 0;

    // Если наложенный платеж был оплачен, но посылку возвращают, генерируем код возврата денег
    if ($parcel['is_cod_paid'] && !$parcel['is_cod_issued'] && !$parcel['cod_refund_issued']) {
        $refund_code = 'REF-' . rand(1000, 9999) . '-' . rand(1000, 9999);
    }

    // Если посылка отправлялась с ПВЗ, при возврате она должна вернуться на тот же ПВЗ
    $target_pvz = $parcel['sender_pvz'];

    $stmt = $pdo->prepare("UPDATE parcels SET is_return = 1, refund_code = :rc, pickup_point = :pvz, shelf = NULL WHERE id = :id");
    $stmt->execute(['rc' => $refund_code, 'pvz' => $target_pvz, 'id' => $id]);

    $status_text = "Оформлен возврат [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
    if ($refund_code) {
        $status_text .= " | Сформирован код возврата наложенного платежа: " . $refund_code;
    }
    $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
    $stmt->execute(['pid' => $id, 'txt' => $status_text]);

    // Уведомляем обоих участников
    notifyUser($parcel['sender_id'], "Посылка {$parcel['track_code']} возвращается к вам. Ожидайте уведомления о прибытии.");
    notifyUser($parcel['recipient_id'], "Для посылки {$parcel['track_code']} оформлен возврат отправителю.");

} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

header("Location: dashboard.php");
exit;
