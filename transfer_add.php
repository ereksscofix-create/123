<?php
// transfer_add.php — Отправка денежного перевода
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$shift = getOpenShift($user['id']);
if (!$shift) die("Ошибка: Смена не открыта. <a href='shift_manage.php'>ОТКРЫТЬ СМЕНУ</a>");

$success = '';
$error = '';
$receipt_html = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = (int)$_POST['sender_id'];
    $recipient_id = (int)$_POST['recipient_id'];
    $amount = (float)$_POST['amount'];
    $method = $_POST['method'];
    $receipt_no = 'TR' . date('ymd') . rand(1000, 9999);

    $fee = $amount * 0.03; // Комиссия 3%
    $total = $amount + $fee;

    $code = 'TR' . rand(100000, 999999) . 'BY';

    try {
        $stmt = $pdo->prepare("INSERT INTO money_transfers (transfer_code, sender_id, recipient_id, amount, fee, status, payment_method, receipt_no)
                               VALUES (:code, :sid, :rid, :amt, :fee, 'paid', :meth, :rec)");
        $stmt->execute([
            'code' => $code, 'sid' => $sender_id, 'rid' => $recipient_id,
            'amt' => $amount, 'fee' => $fee, 'meth' => $method, 'rec' => $receipt_no
        ]);
        $transfer_id = $pdo->lastInsertId();

        // Логируем доход (Сумма перевода + Комиссия)
        logTransaction($shift['id'], $user['id'], 'income', 'Денежный перевод (Прием)', $total, $transfer_id);

        notifyUser($recipient_id, "Вам отправлен денежный перевод на сумму $amount BYN. Код: $code");

        $success = "Перевод успешно оформлен! Код: <strong>$code</strong>";

        // Формируем чек
        $receipt_html = "
            <div class='receipt shadow-sm p-4 bg-white mx-auto' style='max-width:350px; font-family:monospace;'>
                <div class='text-center border-bottom mb-3 pb-2'><h5>EHPST POST</h5> ПЕРЕВОД ПРИНЯТ</div>
                <div>КОД: <strong>$code</strong></div>
                <div>СУММА: $amount BYN</div>
                <div>СБОР (3%): $fee BYN</div>
                <div class='border-top mt-2 pt-2 fw-bold'>ИТОГО: $total BYN</div>
                <div class='text-center mt-4 small'>Сообщите код получателю</div>
                <button class='btn btn-sm btn-outline-primary w-100 mt-3 d-print-none' onclick='window.print()'>ПЕЧАТЬ ЧЕКА</button>
            </div>";

    } catch (Exception $e) { $error = $e->getMessage(); }
}

$page_title = "Оформить перевод — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <?php if($success): echo $receipt_html; echo "<div class='text-center mt-3'><a href='dashboard.php' class='btn btn-light rounded-pill'>В дашборд</a></div>"; ?>
        <?php else: ?>
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-send-fill me-2"></i>Новый перевод</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <form method="post">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">ID Отправителя</label>
                            <input type="number" name="sender_id" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">ID Получателя</label>
                            <input type="number" name="recipient_id" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Сумма (BYN)</label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-lg" required>
                            <div class="form-text">Комиссия системы: 3%</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Способ оплаты</label>
                            <select name="method" class="form-select">
                                <option value="Карта">Карта</option>
                                <option value="Наличные">Наличные</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light border rounded-3">
                                <label class="form-label small fw-bold text-muted d-block mb-1">Номер чека (Авто)</label>
                                <div class="h6 mb-0 fw-bold">TR<?php echo date('ymd'); ?>XXXX</div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill py-3 mt-4 fw-bold">ОПЛАТИТЬ И ОТПРАВИТЬ</button>
                    <a href="dashboard.php" class="btn btn-link w-100 text-muted mt-2">Отмена</a>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
