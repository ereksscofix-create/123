<?php
// parcel_refund.php — Чек возврата денег
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
if (isset($_POST['refund'])) {
    try {
        // Оформляем возврат денег
        $stmt = $pdo->prepare("UPDATE parcels SET is_paid = 0, is_refunded = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $status_text = "ВОЗВРАТ ДЕНЕЖНЫХ СРЕДСТВ [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        $success = true;
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$page_title = "Возврат денег " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <?php if (!$success): ?>
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-danger text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-counterclockwise me-2"></i>Оформление возврата денег</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="alert alert-warning">Вы собираетесь оформить возврат денежных средств за услуги связи. Посылка будет помечена как неоплаченная.</div>

                <div class="text-center mb-4 border p-3 rounded-3">
                    <div class="text-muted small text-uppercase fw-bold">Сумма к возврату клиенту</div>
                    <div class="h2 fw-extrabold text-danger"><?php echo number_format($parcel['cost'], 2); ?> BYN</div>
                </div>

                <form method="post">
                    <button type="submit" name="refund" class="btn btn-danger btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                        <i class="bi bi-check-circle me-2"></i>ПОДТВЕРДИТЬ ВОЗВРАТ
                    </button>
                    <a href="dashboard.php" class="btn btn-light w-100 mt-3 rounded-pill">Отмена</a>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="receipt-container">
            <div class="receipt shadow-lg border-top border-danger border-5">
                <div class="text-center mb-4 border-bottom pb-3">
                    <h3 class="fw-bold mb-0">EHPST POST</h3>
                    <div class="small text-muted">ЧЕК ВОЗВРАТА ДЕНЕГ</div>
                </div>
                <div class="receipt-row"><span>НОМЕР ТРЕКА:</span> <strong><?php echo e($parcel['track_code']); ?></strong></div>
                <div class="receipt-row"><span>ДАТА:</span> <strong><?php echo date('d.m.Y H:i:s'); ?></strong></div>
                <div class="receipt-row border-bottom mb-2 pb-2"><span>ТИП:</span> <strong class="text-danger">ВОЗВРАТ СРЕДСТВ</strong></div>

                <div class="receipt-row"><span>КЛИЕНТ:</span> <strong><?php echo e($parcel['s_name']); ?></strong></div>
                <div class="receipt-row border-top mt-3 pt-2"><span>СУММА ВОЗВРАТА:</span> <span class="h4 mb-0 fw-bold text-danger"><?php echo number_format($parcel['cost'], 2); ?> BYN</span></div>

                <div class="text-center mt-5 d-print-none">
                    <button class="btn btn-danger rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Печать чека возврата</button>
                    <a href="dashboard.php" class="btn btn-light rounded-pill px-4 ms-2">В дашборд</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

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
