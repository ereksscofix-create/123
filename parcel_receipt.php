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
                <div class="receipt-row border-bottom mb-3 pb-2"><span>ПОЛУЧАТЕЛЬ:</span> <strong><?php echo e($parcel['r_name'] ?: $parcel['r_login']); ?></strong></div>

                <div class="mb-3">
                    <div class="small fw-bold text-uppercase mb-2 border-bottom pb-1">Позиции чека:</div>
                    <table class="w-100 x-small" style="font-size: 12px; border-collapse: collapse;">
                        <tr class="border-bottom">
                            <th class="text-start py-1">Услуга</th>
                            <th class="text-end py-1">Сумма</th>
                        </tr>
                        <tr>
                            <td class="py-1">Доставка (Тариф: <?php echo e($parcel['tariff']); ?>, <?php echo number_format($parcel['weight'], 3); ?> кг)</td>
                            <td class="text-end py-1"><?php echo number_format($parcel['base_cost'] ?: $parcel['cost'], 2); ?></td>
                        </tr>
                        <?php if($parcel['cod_fee'] > 0): ?>
                        <tr>
                            <td class="py-1">Комиссия за наложенный платеж</td>
                            <td class="text-end py-1"><?php echo number_format($parcel['cod_fee'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if($parcel['dv_fee'] > 0): ?>
                        <tr>
                            <td class="py-1">Страхование (Объявл. ценность)</td>
                            <td class="text-end py-1"><?php echo number_format($parcel['dv_fee'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if($parcel['inv_fee'] > 0): ?>
                        <tr>
                            <td class="py-1">Оформление описи вложения</td>
                            <td class="text-end py-1"><?php echo number_format($parcel['inv_fee'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if(($parcel['edit_fee'] ?? 0) > 0): ?>
                        <tr>
                            <td class="py-1">Сбор за изменение данных (4%)</td>
                            <td class="text-end py-1"><?php echo number_format($parcel['edit_fee'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if($parcel['is_cod_paid']): ?>
                        <tr class="border-top">
                            <td class="py-1 fw-bold">Сумма наложенного платежа</td>
                            <td class="text-end py-1 fw-bold"><?php echo number_format($parcel['cod'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if($parcel['loyalty_spent'] > 0): ?>
                        <tr class="text-danger border-top">
                            <td class="py-1 fw-bold">Списание бонусов</td>
                            <td class="text-end py-1 fw-bold">-<?php echo number_format($parcel['loyalty_spent'], 2); ?> Б.</td>
                        </tr>
                        <?php endif; ?>
                        <?php if($parcel['loyalty_earned'] > 0): ?>
                        <tr class="text-success">
                            <td class="py-1 fw-bold">Начислено бонусов</td>
                            <td class="text-end py-1 fw-bold">+<?php echo number_format($parcel['loyalty_earned'], 2); ?> Б.</td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php if(!empty($parcel['inventory'])): ?>
                <div class="mb-3 p-2 bg-light rounded" style="font-size: 11px;">
                    <div class="fw-bold mb-1">ОПИСЬ ВЛОЖЕНИЯ:</div>
                    <div><?php echo nl2br(e($parcel['inventory'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="receipt-row border-top mt-2 pt-2 fw-bold" style="font-size: 18px;">
                    <span>ИТОГО К ОПЛАТЕ:</span>
                    <span><?php echo number_format($parcel['cost'] + ($parcel['is_cod_paid'] ? $parcel['cod'] : 0) - $parcel['loyalty_spent'], 2); ?> BYN</span>
                </div>

                <?php
                // Получаем актуальный остаток бонусов если карта была использована
                $stmt = $pdo->prepare("SELECT balance FROM loyalty_cards WHERE user_id = :uid");
                $stmt->execute(['uid' => $parcel['sender_id']]); // Для услуг связи - отправитель
                $l_balance = $stmt->fetchColumn();
                if ($l_balance !== false):
                ?>
                <div class="receipt-row small text-muted border-top mt-2 pt-2">
                    <span>ОСТАТОК БОНУСОВ:</span>
                    <span><?php echo number_format($l_balance, 2); ?> Б.</span>
                </div>
                <?php endif; ?>

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
