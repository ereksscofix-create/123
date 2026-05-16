<?php
// parcel_pay.php — Оплата посылки работником
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
    $stmt = $pdo->prepare("SELECT p.*, s.name as s_name, r.name as r_name
                           FROM parcels p
                           LEFT JOIN users s ON p.sender_id = s.id
                           LEFT JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");
} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$success = false;
if (isset($_POST['pay'])) {
    try {
        $stmt = $pdo->prepare("UPDATE parcels SET is_paid = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $status_text = "Оплачено [" . date('d.m.Y H:i') . "] (Кассир: " . ($user['name'] ?: $user['login']) . ")";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        $success = true;
        // Можно обновить локальный объект
        $parcel['is_paid'] = 1;
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$page_title = "Оплата " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <?php if (!$success): ?>
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-success text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2"></i>Прием оплаты</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="text-muted small text-uppercase fw-bold">К оплате за посылку <?php echo e($parcel['track_code']); ?></div>
                    <div class="display-4 fw-extrabold text-success"><?php echo number_format($parcel['cost'], 2); ?> BYN</div>
                </div>

                <ul class="list-group list-group-flush mb-4 rounded-3 border">
                    <li class="list-group-item d-flex justify-content-between"><span>Отправитель:</span> <strong><?php echo e($parcel['s_name']); ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Получатель:</span> <strong><?php echo e($parcel['r_name']); ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Вес:</span> <strong><?php echo number_format($parcel['weight'], 3); ?> кг</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Тариф:</span> <strong><?php echo e($parcel['tariff']); ?></strong></li>
                </ul>

                <form method="post">
                    <button type="submit" name="pay" class="btn btn-success btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                        <i class="bi bi-check-circle me-2"></i>Подтвердить оплату
                    </button>
                    <a href="dashboard.php" class="btn btn-light w-100 mt-3 rounded-pill">Отмена</a>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- ЧЕК ОБ ОПЛАТЕ -->
        <div class="receipt-container">
            <div class="receipt shadow-lg">
                <div class="text-center mb-4 border-bottom pb-3">
                    <h3 class="fw-bold mb-0">EHPST POST</h3>
                    <div class="small text-muted">Квитанция об оплате услуг связи</div>
                </div>
                <div class="receipt-row"><span>НОМЕР ТРЕКА:</span> <strong><?php echo e($parcel['track_code']); ?></strong></div>
                <div class="receipt-row"><span>ДАТА:</span> <strong><?php echo date('d.m.Y H:i:s'); ?></strong></div>
                <div class="receipt-row"><span>ОТПРАВИТЕЛЬ:</span> <strong><?php echo e($parcel['s_name']); ?></strong></div>
                <div class="receipt-row"><span>ПОЛУЧАТЕЛЬ:</span> <strong><?php echo e($parcel['r_name']); ?></strong></div>
                <div class="receipt-row"><span>ВЕС:</span> <strong><?php echo number_format($parcel['weight'], 3); ?> кг</strong></div>
                <div class="receipt-row border-top mt-3 pt-2"><span>ИТОГО:</span> <span class="h4 mb-0 fw-bold"><?php echo number_format($parcel['cost'], 2); ?> BYN</span></div>
                <div class="text-center mt-5">
                    <div class="mb-3 small opacity-75">СПАСИБО, ЧТО ВЫБИРАЕТЕ НАС!</div>
                    <div class="d-print-none">
                        <button class="btn btn-primary rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Печать чека</button>
                        <a href="dashboard.php" class="btn btn-light rounded-pill px-4 ms-2">В дашборд</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.receipt-container { display: flex; justify-content: center; }
.receipt { background: #fff; width: 100%; max-width: 400px; padding: 40px; border-radius: 4px; font-family: 'Courier New', Courier, monospace; position: relative; }
.receipt::before, .receipt::after { content: ""; position: absolute; left: 0; right: 0; height: 10px; background-size: 20px 20px; background-repeat: repeat-x; }
.receipt::before { top: -10px; background-image: radial-gradient(circle at 10px 15px, #fff 10px, transparent 11px); }
.receipt::after { bottom: -10px; background-image: radial-gradient(circle at 10px -5px, #fff 10px, transparent 11px); }
.receipt-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
@media print {
    body * { visibility: hidden; }
    .receipt, .receipt * { visibility: visible; }
    .receipt { position: absolute; left: 0; top: 0; margin: 0; box-shadow: none; width: 100%; }
    .d-print-none { display: none !important; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
