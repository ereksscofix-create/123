<?php
// parcel_pay.php — Оплата посылки работником
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

// Проверка открытой смены
$shift = getOpenShift($user['id']);
if (!$shift) die("Ошибка: Смена не открыта. <a href='shift_manage.php'>ОТКРЫТЬ СМЕНУ</a>");

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
$error = '';
$loyalty_card = null;
$confirm_required = false;

if (isset($_POST['check_loyalty'])) {
    $card_no = trim($_POST['loyalty_card_no'] ?? '');
    $loyalty_card = getLoyaltyCard($card_no);
    if (!$loyalty_card) $error = "Карта лояльности не найдена.";
}

if (isset($_POST['send_code'])) {
    $card_id = (int)$_POST['card_id'];
    $points_to_spend = (float)$_POST['points_to_spend'];
    $code = rand(1000, 9999);
    $stmt = $pdo->prepare("INSERT INTO loyalty_confirm_codes (card_id, code, amount, expires_at) VALUES (:cid, :c, :a, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $stmt->execute(['cid' => $card_id, 'c' => $code, 'a' => $points_to_spend]);

    $stmt = $pdo->prepare("SELECT user_id FROM loyalty_cards WHERE id = :id");
    $stmt->execute(['id' => $card_id]);
    $uid = $stmt->fetchColumn();
    notifyUser($uid, "Код подтверждения списания бонусов: $code. Сумма: $points_to_spend Б.", 'info', true);
    $confirm_required = true;
    $loyalty_card = getLoyaltyCard($_POST['loyalty_card_no']); // Restore card info
}

if (isset($_POST['pay'])) {
    $method = $_POST['method'] ?? 'Карта';
    $receipt = 'RC' . date('ymd') . rand(1000, 9999);
    $cash_in = (float)($_POST['cash_amount'] ?? 0);
    $final_cost = (float)$parcel['cost'];

    $points_spent = (float)($_POST['points_spent'] ?? 0);
    $conf_code = trim($_POST['confirm_code'] ?? '');

    try {
        if ($points_spent > 0) {
            $card_id = (int)$_POST['card_id'];
            $stmt = $pdo->prepare("SELECT * FROM loyalty_confirm_codes WHERE card_id = :cid AND code = :code AND amount = :amt AND expires_at > NOW() LIMIT 1");
            $stmt->execute(['cid' => $card_id, 'code' => $conf_code, 'amt' => $points_spent]);
            if (!$stmt->fetch()) {
                throw new Exception("Неверный или истекший код подтверждения бонусов.");
            }
            $final_cost -= $points_spent;
            if ($final_cost < 0) $final_cost = 0;

            // Списываем бонусы
            $stmt = $pdo->prepare("UPDATE loyalty_cards SET balance = balance - :pts WHERE id = :cid");
            $stmt->execute(['pts' => $points_spent, 'cid' => $card_id]);

            $pdo->prepare("INSERT INTO loyalty_transactions (card_id, amount, type) VALUES (:cid, :amt, 'spend')")
                ->execute(['cid' => $card_id, 'amt' => $points_spent]);
        }

        // Начисляем бонусы если карта указана
        $earned = 0;
        if (!empty($_POST['loyalty_card_no'])) {
            $l_card = getLoyaltyCard($_POST['loyalty_card_no']);
            if ($l_card) {
                $pct = getLoyaltyPercent($l_card['level']);
                $earned = $final_cost * $pct;

                $stmt = $pdo->prepare("UPDATE loyalty_cards SET balance = balance + :e, payments_count = payments_count + 1 WHERE id = :cid");
                $stmt->execute(['e' => $earned, 'cid' => $l_card['id']]);

                $pdo->prepare("INSERT INTO loyalty_transactions (card_id, amount, type, expires_at) VALUES (:cid, :amt, 'earn', DATE_ADD(NOW(), INTERVAL 1 YEAR))")
                    ->execute(['cid' => $l_card['id'], 'amt' => $earned]);

                updateLoyaltyLevel($l_card['id']);
            }
        }

        $stmt = $pdo->prepare("UPDATE parcels SET is_paid = 1, payment_method = :m, receipt_no = :r, loyalty_earned = :e, loyalty_spent = :s WHERE id = :id");
        $stmt->execute(['m' => $method, 'r' => $receipt, 'e' => $earned, 's' => $points_spent, 'id' => $id]);

        logTransaction($shift['id'], $user['id'], 'income', 'Услуги связи', $final_cost, $id);

        $status_text = "Оплачено ($method, №$receipt). Бонусы: -$points_spent / +$earned [" . date('d.m.Y H:i') . "]";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        $success = true;
        $parcel['is_paid'] = 1;
        $parcel['receipt_no'] = $receipt;
        $parcel['payment_method'] = $method;
        $parcel['loyalty_earned'] = $earned;
        $parcel['loyalty_spent'] = $points_spent;
    } catch (Exception $e) { $error = $e->getMessage(); }
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
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 rounded-3 mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

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

                <form method="post" id="unifiedPayForm">
                    <!-- Секция лояльности -->
                    <div class="mb-4 p-3 bg-light rounded-3 border border-primary border-opacity-10">
                        <label class="form-label fw-bold small text-uppercase"><i class="bi bi-star-fill text-warning me-1"></i>Программа лояльности</label>
                        <div class="input-group">
                            <input type="text" name="loyalty_card_no" class="form-control" placeholder="Номер карты 5000..." value="<?php echo e($_POST['loyalty_card_no'] ?? ''); ?>">
                            <button type="submit" name="check_loyalty" class="btn btn-primary">Проверить</button>
                        </div>

                        <?php if ($loyalty_card): ?>
                            <div class="mt-3 p-3 bg-white rounded-3 shadow-sm">
                                <div class="d-flex justify-content-between">
                                    <span class="small text-muted">Уровень: <b><?php echo strtoupper($loyalty_card['level']); ?></b></span>
                                    <span class="small text-muted">Баланс: <b><?php echo number_format($loyalty_card['balance'], 0); ?> Б.</b></span>
                                </div>
                                <input type="hidden" name="card_id" value="<?php echo $loyalty_card['id']; ?>">

                                <?php if (!$confirm_required && empty($_POST['confirm_code'])): ?>
                                    <div class="mt-3">
                                        <label class="form-label x-small fw-bold">Списать бонусы?</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="points_to_spend" class="form-control" max="<?php echo min($loyalty_card['balance'], $parcel['cost']); ?>" placeholder="Сумма списания">
                                            <button type="submit" name="send_code" class="btn btn-warning">Получить код</button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3">
                                        <label class="form-label x-small fw-bold text-success"><?php echo $confirm_required ? 'Код подтверждения отправлен!' : 'Бонусы готовы к списанию'; ?></label>
                                        <input type="hidden" name="points_spent" value="<?php echo $_POST['points_to_spend'] ?? $_POST['points_spent']; ?>">
                                        <input type="text" name="confirm_code" class="form-control form-control-sm" placeholder="Введите код из личного кабинета" required value="<?php echo e($_POST['confirm_code'] ?? ''); ?>">
                                        <div class="mt-2 small">К списанию: <b><?php echo $_POST['points_to_spend'] ?? $_POST['points_spent']; ?> Б.</b></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase">Способ оплаты</label>
                        <div class="d-flex gap-3 mb-3">
                            <input type="radio" class="btn-check" name="method" id="payCard" value="Карта" checked onchange="toggleCash(false)">
                            <label class="btn btn-outline-primary flex-grow-1 rounded-pill" for="payCard"><i class="bi bi-credit-card me-2"></i>Карта</label>

                            <input type="radio" class="btn-check" name="method" id="payCash" value="Наличные" onchange="toggleCash(true)">
                            <label class="btn btn-outline-primary flex-grow-1 rounded-pill" for="payCash"><i class="bi bi-cash me-2"></i>Наличные</label>
                        </div>
                    </div>

                    <div id="cashInputSection" style="display:none;" class="mb-4 animate-fade-in">
                        <label class="form-label fw-bold small text-uppercase">Получено от клиента</label>
                        <div class="input-group input-group-lg">
                            <input type="number" step="0.01" name="cash_amount" id="cashAmount" class="form-control" placeholder="0.00" oninput="calcChange(<?php echo $parcel['cost']; ?>)">
                            <span class="input-group-text bg-light">BYN</span>
                        </div>
                        <div class="mt-2 h5 fw-bold text-primary">Сдача: <span id="changeDisplay">0.00</span> BYN</div>
                    </div>

                    <div class="mb-4 border p-3 rounded-3 bg-light">
                        <label class="form-label fw-bold small text-uppercase text-muted d-block mb-1">Номер чека (Авто)</label>
                        <div class="h5 mb-0 fw-bold">RC<?php echo date('ymd'); ?>XXXX</div>
                        <div class="form-text">Номер будет сгенерирован автоматически после подтверждения.</div>
                    </div>

                    <button type="submit" name="pay" class="btn btn-success btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                        <i class="bi bi-check-circle me-2"></i>Подтвердить оплату
                    </button>
                    <a href="dashboard.php" class="btn btn-light w-100 mt-3 rounded-pill">Отмена</a>
                </form>

                <script>
                function toggleCash(show) {
                    document.getElementById('cashInputSection').style.display = show ? 'block' : 'none';
                    document.getElementById('cashAmount').required = show;
                }
                function calcChange(cost) {
                    const received = parseFloat(document.getElementById('cashAmount').value) || 0;
                    const change = received - cost;
                    document.getElementById('changeDisplay').textContent = change >= 0 ? change.toFixed(2) : "0.00";
                }
                </script>
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
                <div class="receipt-row"><span>ОПЕРАЦИЯ №:</span> <strong><?php echo e($parcel['receipt_no'] ?? '---'); ?></strong></div>
                <div class="receipt-row border-bottom mb-2 pb-2"><span>ТИП:</span> <strong>ОПЛАТА (<?php echo e($parcel['payment_method'] ?? 'Карта'); ?>)</strong></div>

                <div class="receipt-row"><span>ОТПРАВИТЕЛЬ:</span> <strong><?php echo e($parcel['s_name']); ?></strong></div>
                <div class="receipt-row border-bottom mb-2 pb-2"><span>ПОЛУЧАТЕЛЬ:</span> <strong><?php echo e($parcel['r_name']); ?></strong></div>

                <?php if(isset($_POST['method']) && $_POST['method'] === 'Наличные'): ?>
                    <div class="receipt-row small"><span>ПРИНЯТО:</span> <span><?php echo number_format((float)$_POST['cash_amount'], 2); ?> BYN</span></div>
                    <div class="receipt-row small mb-2"><span>СДАЧА:</span> <span><?php echo number_format((float)$_POST['cash_amount'] - (float)$parcel['cost'], 2); ?> BYN</span></div>
                <?php endif; ?>

                <?php if (isset($_POST['points_spent']) && $_POST['points_spent'] > 0): ?>
                    <div class="receipt-row small"><span>БОНУСОВ СПИСАНО:</span> <span><?php echo number_format((float)$_POST['points_spent'], 0); ?> Б.</span></div>
                <?php endif; ?>

                <div class="receipt-row border-top mt-3 pt-2"><span>ИТОГО К ОПЛАТЕ:</span> <span class="h4 mb-0 fw-bold"><?php echo number_format($parcel['cost'] - (float)($_POST['points_spent'] ?? 0), 2); ?> BYN</span></div>
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
