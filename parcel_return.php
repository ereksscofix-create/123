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

$error = '';
$success = '';
$parcel = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $return_fee = (float)($_POST['return_fee'] ?? 0);

        // Логика возврата
        $refund_code = '';

        // Если наложенный платеж был оплачен, но посылку возвращают, генерируем код возврата денег
        if ($parcel['is_cod_paid'] && !$parcel['is_cod_issued'] && !$parcel['cod_refund_issued']) {
            $refund_code = 'REF-' . rand(1000, 9999) . '-' . rand(1000, 9999);
        }

        // Если посылка отправлялась с ПВЗ, при возврате она должна вернуться на тот же ПВЗ
        $target_pvz = $parcel['sender_pvz'];

        $stmt = $pdo->prepare("UPDATE parcels SET is_return = 1, refund_code = :rc, pickup_point = :pvz, shelf = NULL, return_fee = :rf WHERE id = :id");
        $stmt->execute(['rc' => $refund_code, 'pvz' => $target_pvz, 'rf' => $return_fee, 'id' => $id]);

        $status_text = "Оформлен возврат [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        if ($return_fee > 0) {
            $status_text .= " | Начислена комиссия за возврат: " . number_format($return_fee, 2) . " BYN";
        }
        if ($refund_code) {
            $status_text .= " | Сформирован код возврата наложенного платежа: " . $refund_code;
        }
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        // Уведомляем обоих участников
        notifyUser($parcel['sender_id'], "Посылка {$parcel['track_code']} возвращается к вам. Ожидайте уведомления о прибытии.");
        notifyUser($parcel['recipient_id'], "Для посылки {$parcel['track_code']} оформлен возврат отправителю.");

        header("Location: dashboard.php");
        exit;
    }

} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

$page_title = "Оформление возврата — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-warning text-dark p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Оформление возврата</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="mb-4 text-center">
                    <div class="text-muted small text-uppercase fw-bold">Возврат посылки:</div>
                    <div class="h3 fw-bold text-primary"><?php echo e($parcel['track_code']); ?></div>
                    <div class="mt-2 text-muted">Посылка будет отправлена обратно отправителю.</div>
                </div>

                <form method="post">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Комиссия за возврат (BYN)</label>
                        <input type="number" name="return_fee" class="form-control form-control-lg border-2 shadow-none" step="0.01" value="0.00">
                        <div class="form-text">Если возврат платный, введите сумму. Если бесплатно — оставьте 0.00.</div>
                    </div>

                    <div class="alert alert-info small border-0 shadow-sm mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <?php if ($parcel['is_cod_paid']): ?>
                            Будет сгенерирован код для возврата наложенного платежа (<?php echo number_format($parcel['cod'], 2); ?> BYN) получателю.
                        <?php else: ?>
                            Наложенный платеж не был оплачен, возврат средств не требуется.
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-warning w-100 btn-lg rounded-pill shadow-sm fw-bold text-uppercase">
                            <i class="bi bi-check2-circle me-2"></i>Подтвердить возврат
                        </button>
                        <a href="dashboard.php" class="btn btn-link w-100 text-muted mt-2 text-decoration-none small fw-bold">ОТМЕНА</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
exit;
