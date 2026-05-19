<?php
// parcel_pay.php — Оплата посылки работником
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Прячем системные ошибки

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';

try {
    checkLogin();
    $user = currentUser();

    if (!$user || $user['role'] !== 'worker') {
        throw new Exception("Доступ запрещен. Только для работников.");
    }

    // Проверка открытой смены
    $shift = getOpenShift($user['id']);
    if (!$shift) {
        $error_title = "Смена не открыта!";
        $error_text = "Для приема платежей необходимо сначала открыть кассовую смену.";
        $error_btn = "<a href='shift_manage.php' class='btn btn-primary rounded-pill px-5'>ОТКРЫТЬ СМЕНУ</a>";
        throw new Exception("shift_not_open");
    }

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        throw new Exception("Идентификатор посылки не указан.");
    }

    $stmt = $pdo->prepare("SELECT p.*, s.name as s_name, r.name as r_name
                           FROM parcels p
                           LEFT JOIN users s ON p.sender_id = s.id
                           LEFT JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();

    if (!$parcel) {
        throw new Exception("Посылка #$id не найдена.");
    }

} catch (Exception $e) {
    if (ob_get_level()) ob_clean();
    include __DIR__ . '/header.php';
    $msg = $e->getMessage();
    if ($msg === "shift_not_open") {
        echo "<div class='alert alert-danger p-5 text-center rounded-4 shadow-sm'>
                <i class='bi bi-exclamation-octagon display-1 d-block mb-4'></i>
                <h3 class='fw-bold'>{$error_title}</h3>
                <p>{$error_text}</p>
                {$error_btn}
              </div>";
    } else {
        echo "<div class='alert alert-warning p-5 text-center rounded-4 shadow-sm'>
                <i class='bi bi-exclamation-triangle display-1 d-block mb-4'></i>
                <h3 class='fw-bold'>Ошибка</h3>
                <p>" . e($msg) . "</p>
                <a href='dashboard.php' class='btn btn-light rounded-pill px-5'>В ДАШБОРД</a>
              </div>";
    }
    include __DIR__ . '/footer.php';
    exit;
}

$success = false;
$error = '';
$loyalty_card = null;
$confirm_required = false;

// Всегда подгружаем карту, если номер передан
if (!empty($_POST['loyalty_card_no'])) {
    $loyalty_card = getLoyaltyCard($_POST['loyalty_card_no']);
}

if (isset($_POST['check_loyalty'])) {
    if (!$loyalty_card) $error = "Карта лояльности не найдена.";
}

if (isset($_POST['send_code'])) {
    try {
        $card_id = (int)$_POST['card_id'];
        $points_to_spend = (float)$_POST['points_to_spend'];

        // Очистка старых кодов
        $pdo->prepare("DELETE FROM loyalty_confirm_codes WHERE card_id = :cid")->execute(['cid' => $card_id]);

        $code = rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO loyalty_confirm_codes (card_id, code, amount, expires_at) VALUES (:cid, :c, :a, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        $stmt->execute(['cid' => $card_id, 'c' => $code, 'a' => $points_to_spend]);

        $stmt = $pdo->prepare("SELECT user_id FROM loyalty_cards WHERE id = :id");
        $stmt->execute(['id' => $card_id]);
        $uid = $stmt->fetchColumn();
        notifyUser($uid, "Код подтверждения списания бонусов: $code. Сумма: $points_to_spend Б.", 'info', true);
        $confirm_required = true;
    } catch (Exception $e) { $error = "Ошибка при отправке кода: " . $e->getMessage(); }
}

if (isset($_POST['pay'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Ошибка безопасности (CSRF). Обновите страницу.";
    } else {
        $method = $_POST['method'] ?? 'Карта';
        $receipt = 'RC' . date('ymd') . rand(1000, 9999);
        $cash_in = (float)($_POST['cash_amount'] ?? 0);
        $final_cost = (float)$parcel['cost'];

        $points_spent = (float)($_POST['points_spent'] ?? 0);
        $conf_code = trim($_POST['confirm_code'] ?? '');
        $card_id = (int)($_POST['card_id'] ?? 0);

        try {
            $pdo->beginTransaction();

            if ($points_spent > 0 && $card_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM loyalty_confirm_codes WHERE card_id = :cid AND code = :code AND amount = :amt AND expires_at > NOW() LIMIT 1");
                $stmt->execute(['cid' => $card_id, 'code' => $conf_code, 'amt' => $points_spent]);
                if (!$stmt->fetch()) {
                    throw new Exception("Неверный или истекший код подтверждения бонусов.");
                }
                $final_cost -= $points_spent;
                if ($final_cost < 0) $final_cost = 0;

                // Проверяем баланс перед списанием
                $stmt = $pdo->prepare("SELECT balance FROM loyalty_cards WHERE id = :cid");
                $stmt->execute(['cid' => $card_id]);
                $curr_bal = (float)$stmt->fetchColumn();
                if ($curr_bal < $points_spent) {
                    throw new Exception("Недостаточно бонусов на карте.");
                }

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

            $stmt = $pdo->prepare("UPDATE parcels SET is_paid = 1, payment_method = :m, receipt_no = :r, loyalty_earned = loyalty_earned + :e, loyalty_spent = loyalty_spent + :s WHERE id = :id");
            $stmt->execute(['m' => $method, 'r' => $receipt, 'e' => $earned, 's' => $points_spent, 'id' => $id]);

            logTransaction($shift['id'], $user['id'], 'income', 'Услуги связи', $final_cost, $id);

            $status_text = "Оплачено ($method, №$receipt). Бонусы: -$points_spent / +$earned [" . date('d.m.Y H:i') . "]";
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $id, 'txt' => $status_text]);

            $pdo->commit();
            header("Location: parcel_receipt.php?id=$id");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
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
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="card_id" value="<?php echo $loyalty_card['id'] ?? ''; ?>">

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
                                    <span class="small text-muted">Баланс: <b><?php echo number_format($loyalty_card['balance'], 2); ?> Б.</b></span>
                                </div>

                                <?php if (!$confirm_required && empty($_POST['confirm_code'])): ?>
                                    <div class="mt-3">
                                        <label class="form-label x-small fw-bold">Списать бонусы?</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" name="points_to_spend" class="form-control" max="<?php echo min($loyalty_card['balance'], $parcel['cost']); ?>" placeholder="Сумма списания">
                                            <button type="submit" name="send_code" class="btn btn-warning">Получить код</button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3">
                                        <label class="form-label x-small fw-bold text-success"><?php echo $confirm_required ? 'Код подтверждения отправлен!' : 'Бонусы готовы к списанию'; ?></label>
                                        <input type="hidden" name="points_spent" value="<?php echo e($_POST['points_to_spend'] ?? $_POST['points_spent'] ?? 0); ?>">
                                        <input type="text" name="confirm_code" class="form-control form-control-sm" placeholder="Введите код из личного кабинета" required value="<?php echo e($_POST['confirm_code'] ?? ''); ?>">
                                        <div class="mt-2 small">К списанию: <b><?php echo number_format((float)($_POST['points_to_spend'] ?? $_POST['points_spent'] ?? 0), 2); ?> Б.</b></div>
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
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
