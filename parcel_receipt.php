<?php
// parcel_receipt.php — Просмотр чека об оплате
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID посылки не указан.");

try {
    $stmt = $pdo->prepare("SELECT p.*, s.name as s_name, s.login as s_login, r.name as r_name, r.login as r_login
                           FROM parcels p
                           LEFT JOIN users s ON p.sender_id = s.id
                           LEFT JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");

    // Проверка доступа
    $is_worker = ($user['role'] === 'worker');
    $is_sender = ((int)$parcel['sender_id'] === (int)$user['id']);
    $is_recipient = ((int)$parcel['recipient_id'] === (int)$user['id']);

    if (!$is_worker && !$is_sender && !$is_recipient) {
        die("У вас нет доступа к этому чеку.");
    }

    if (!$parcel['is_paid']) {
        die("Эта посылка еще не оплачена.");
    }

} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$page_title = "Чек " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="receipt-container">
            <div class="receipt shadow-lg">
                <div class="text-center mb-4 border-bottom pb-3">
                    <h3 class="fw-bold mb-0">EHPST POST</h3>
                    <div class="small text-muted">Квитанция об оплате услуг связи</div>
                </div>

                <div class="receipt-row"><span>НОМЕР ТРЕКА:</span> <strong><?php echo e($parcel['track_code']); ?></strong></div>
                <div class="receipt-row"><span>ОПЕРАЦИЯ №:</span> <strong><?php echo e($parcel['receipt_no'] ?: '---'); ?></strong></div>
                <div class="receipt-row border-bottom mb-2 pb-2"><span>ТИП:</span> <strong>ОПЛАТА (<?php echo e($parcel['payment_method'] ?: 'Карта'); ?>)</strong></div>

                <div class="receipt-row"><span>ОТПРАВИТЕЛЬ:</span> <strong><?php echo e($parcel['s_name'] ?: $parcel['s_login']); ?></strong></div>
                <div class="receipt-row border-bottom mb-2 pb-2"><span>ПОЛУЧАТЕЛЬ:</span> <strong><?php echo e($parcel['r_name'] ?: $parcel['r_login']); ?></strong></div>

                <div class="receipt-row border-top mt-3 pt-2"><span>ОСНОВНОЙ ТАРИФ:</span> <strong><?php echo number_format($parcel['base_cost'] ?: $parcel['cost'], 2); ?> BYN</strong></div>
                <?php if($parcel['cod_fee'] > 0): ?>
                    <div class="receipt-row"><span>СБОР ЗА НАЛ. ПЛАТЕЖ:</span> <strong><?php echo number_format($parcel['cod_fee'], 2); ?> BYN</strong></div>
                <?php endif; ?>
                <?php if($parcel['dv_fee'] > 0): ?>
                    <div class="receipt-row"><span>СБОР ЗА ЦЕННОСТЬ:</span> <strong><?php echo number_format($parcel['dv_fee'], 2); ?> BYN</strong></div>
                <?php endif; ?>
                <?php if($parcel['inv_fee'] > 0): ?>
                    <div class="receipt-row"><span>СБОР ЗА ОПИСЬ:</span> <strong><?php echo number_format($parcel['inv_fee'], 2); ?> BYN</strong></div>
                <?php endif; ?>

                <?php if($parcel['is_cod_paid']): ?>
                    <div class="receipt-row border-top mt-2 pt-2"><span>СУММА НАЛОЖ. ПЛАТ.:</span> <strong><?php echo number_format($parcel['cod'], 2); ?> BYN</strong></div>
                <?php endif; ?>

                <div class="receipt-row border-top mt-2 pt-2"><span>ИТОГО К ОПЛАТЕ:</span> <span class="h4 mb-0 fw-bold"><?php echo number_format($parcel['cost'] + ($parcel['is_cod_paid'] ? $parcel['cod'] : 0), 2); ?> BYN</span></div>

                <div class="text-center mt-5">
                    <div class="mb-3 small opacity-75">СПАСИБО, ЧТО ВЫБИРАЕТЕ НАС!</div>
                    <div class="d-print-none d-flex gap-2 justify-content-center">
                        <button class="btn btn-primary rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Печать</button>
                        <a href="dashboard.php" class="btn btn-light rounded-pill px-4">В дашборд</a>
                    </div>
                </div>
            </div>
        </div>
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
