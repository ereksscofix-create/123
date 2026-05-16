<?php
// parcel_pay_cod.php — Оплата наложенного платежа при получении
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
    if ($parcel['cod'] <= 0) die("Для этой посылки не указан наложенный платеж.");
} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$success = false;
if (isset($_POST['pay'])) {
    $method = $_POST['method'] ?? 'Карта';
    $receipt = trim($_POST['receipt_no'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE parcels SET is_cod_paid = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        // Логируем транзакцию (приход наложенного платежа)
        logTransaction($shift['id'], $user['id'], 'income', 'Прием наложенного платежа', $parcel['cod'], $id);

        $status_text = "Оплачен наложенный платеж ($method, №$receipt) [" . date('d.m.Y H:i') . "] (Кассир: " . ($user['name'] ?: $user['login']) . ")";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        // Уведомляем отправителя, что деньги получены
        notifyUser($parcel['sender_id'], "Наложенный платеж за посылку {$parcel['track_code']} оплачен получателем. Ожидайте перевод.");

        $success = true;
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$page_title = "Оплата наложенного платежа " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <?php if (!$success): ?>
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-primary text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2"></i>Наложенный платеж</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="text-muted small text-uppercase fw-bold">Сумма к получению с клиента</div>
                    <div class="display-4 fw-extrabold text-primary"><?php echo number_format($parcel['cod'], 2); ?> BYN</div>
                </div>

                <form method="post">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase">Способ оплаты</label>
                        <div class="d-flex gap-3 mb-3">
                            <input type="radio" class="btn-check" name="method" id="payCard" value="Карта" checked onchange="toggleCash(false)">
                            <label class="btn btn-outline-primary flex-grow-1 rounded-pill" for="payCard"><i class="bi bi-credit-card me-2"></i>Карта</label>

                            <input type="radio" class="btn-check" name="method" id="payCash" value="Наличные" onchange="toggleCash(true)">
                            <label class="btn btn-outline-primary flex-grow-1 rounded-pill" for="payCash"><i class="bi bi-cash me-2"></i>Наличные</label>
                        </div>
                    </div>

                    <div id="cashInputSection" style="display:none;" class="mb-4">
                        <label class="form-label fw-bold small text-uppercase">Получено наличных</label>
                        <input type="number" step="0.01" id="cashAmount" class="form-control form-control-lg" oninput="calcChange(<?php echo $parcel['cod']; ?>)">
                        <div class="mt-2 h5 fw-bold text-success">Сдача: <span id="changeDisplay">0.00</span> BYN</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-uppercase">Номер чека</label>
                        <input type="text" name="receipt_no" class="form-control form-control-lg" required placeholder="Введите номер чека">
                    </div>

                    <button type="submit" name="pay" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                        <i class="bi bi-check-circle me-2"></i>Принять платеж
                    </button>
                    <a href="dashboard.php" class="btn btn-light w-100 mt-3 rounded-pill">Отмена</a>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="receipt-container">
            <div class="receipt shadow-lg border-top border-primary border-5">
                <div class="text-center mb-4 border-bottom pb-3">
                    <h3 class="fw-bold mb-0">EHPST POST</h3>
                    <div class="small text-muted">Квитанция: Наложенный платеж</div>
                </div>
                <div class="receipt-row"><span>ТРЕК-КОД:</span> <strong><?php echo e($parcel['track_code']); ?></strong></div>
                <div class="receipt-row border-bottom mb-2 pb-2"><span>ТИП:</span> <strong>НАЛОЖЕННЫЙ ПЛАТЕЖ</strong></div>
                <div class="receipt-row"><span>СУММА:</span> <span class="h4 mb-0 fw-bold"><?php echo number_format($parcel['cod'], 2); ?> BYN</span></div>
                <div class="text-center mt-5 d-print-none">
                    <button class="btn btn-primary rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Печать</button>
                    <a href="dashboard.php" class="btn btn-light rounded-pill px-4 ms-2">В дашборд</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCash(show) {
    document.getElementById('cashInputSection').style.display = show ? 'block' : 'none';
}
function calcChange(cost) {
    const received = parseFloat(document.getElementById('cashAmount').value) || 0;
    const change = received - cost;
    document.getElementById('changeDisplay').textContent = change >= 0 ? change.toFixed(2) : "0.00";
}
</script>

<style>
.receipt-container { display: flex; justify-content: center; }
.receipt { background: #fff; width: 100%; max-width: 400px; padding: 40px; border-radius: 4px; font-family: 'Courier New', Courier, monospace; }
.receipt-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
@media print {
    body * { visibility: hidden; }
    .receipt, .receipt * { visibility: visible; }
    .receipt { position: absolute; left: 0; top: 0; width: 100%; margin: 0; box-shadow: none; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
