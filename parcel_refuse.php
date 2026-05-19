<?php
// parcel_refuse.php — Отказ получателя от посылки
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан.");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();

    if (!$parcel) die("Посылка не найдена.");
    if ((int)$parcel['recipient_id'] !== (int)$user['id']) die("Доступ запрещен.");

    // Логика отказа
    $cod_return_req = 0;
    $msg = "Получатель ОТКАЗАЛСЯ от посылки {$parcel['track_code']}. Оформлен возврат.";

    // Если наложенный платеж был оплачен, и отправителю уже выдали деньги (is_cod_issued),
    // то отправитель становится должен эти деньги обратно при получении возврата.
    if ($parcel['is_cod_paid'] && $parcel['is_cod_issued']) {
        $cod_return_req = 1;
        $msg .= " ВНИМАНИЕ: Так как наложенный платеж уже был вам выплачен, при получении возврата вам необходимо будет вернуть сумму (" . number_format($parcel['cod'], 2) . " BYN) в кассу.";
    }

    $stmt = $pdo->prepare("UPDATE parcels SET is_return = 1, cod_return_required = :crr, shelf = NULL WHERE id = :id");
    $stmt->execute(['crr' => $cod_return_req, 'id' => $id]);

    $status_text = "Получатель отказался от получения [" . date('d.m.Y H:i') . "]";
    $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
    $stmt->execute(['pid' => $id, 'txt' => $status_text]);

    notifyUser($parcel['sender_id'], $msg, 'warning');

    header("Location: dashboard.php");
    exit;

} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }
