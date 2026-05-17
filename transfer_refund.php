<?php
// transfer_refund.php — Оформление возврата денежного перевода отправителю
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID перевода не указан.");

try {
    $stmt = $pdo->prepare("SELECT * FROM money_transfers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $transfer = $stmt->fetch();

    if (!$transfer) die("Перевод не найден.");
    if ($transfer['status'] !== 'paid') die("Только оплаченные, но не выданные переводы можно вернуть.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $refund_code = 'TR-REF-' . rand(1000, 9999) . '-' . rand(1000, 9999);

        $stmt = $pdo->prepare("UPDATE money_transfers SET status = 'refund_pending', refund_code = :rc WHERE id = :id");
        $stmt->execute(['rc' => $refund_code, 'id' => $id]);

        // Уведомляем отправителя
        $msg = "Ваш перевод {$transfer['transfer_code']} не был забран и возвращен. Код для получения возврата: $refund_code";
        notifyUser($transfer['sender_id'], $msg, 'warning', true);

        header("Location: dashboard.php");
        exit;
    }

} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$page_title = "Оформить возврат перевода — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-warning text-dark p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Возврат перевода</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="mb-4 text-center">
                    <div class="text-muted small text-uppercase fw-bold">Перевод №:</div>
                    <div class="h3 fw-bold text-primary"><?php echo e($transfer['transfer_code']); ?></div>
                    <div class="mt-2 h5">Сумма: <?php echo number_format($transfer['amount'], 2); ?> BYN</div>
                </div>

                <div class="alert alert-info border-0 shadow-sm mb-4">
                    При подтверждении, перевод будет отозван у получателя. Отправителю в личный кабинет придет уведомление с кодом возврата средств.
                </div>

                <form method="post">
                    <button type="submit" class="btn btn-warning btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                        Подтвердить отзыв перевода
                    </button>
                    <a href="dashboard.php" class="btn btn-link w-100 mt-3 text-muted">Отмена</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
