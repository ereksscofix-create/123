<?php
// transfer_refund_issue.php — Выплата возврата перевода отправителю
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$shift = getOpenShift($user['id']);
if (!$shift) die("Ошибка: Смена не открыта.");

$success = '';
$error = '';
$transfer = null;

if (isset($_GET['q']) && !isset($_POST['search'])) {
    $_POST['search'] = true;
    $_POST['q'] = $_GET['q'];
}

if (isset($_POST['search'])) {
    $q = trim($_POST['q'] ?? '');
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT mt.*, u.name as sender_name, u.login as sender_login
                               FROM money_transfers mt
                               JOIN users u ON mt.sender_id = u.id
                               WHERE (mt.transfer_code = :q OR mt.refund_code = :q OR u.name LIKE :lk OR u.login LIKE :lk)
                               AND mt.status = 'refund_pending'
                               ORDER BY mt.id DESC LIMIT 1");
        $stmt->execute(['q' => $q, 'lk' => "%$q%"]);
        $transfer = $stmt->fetch();
        if (!$transfer) $error = "Активный возврат перевода по вашему запросу не найден.";
    }
}

if (isset($_POST['issue']) && isset($_POST['id'])) {
    $tid = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare("SELECT amount FROM money_transfers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $tid]);
        $amt = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE money_transfers SET status = 'refunded', issued_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $tid]);

        // Логируем расход (Выплата из кассы)
        logTransaction($shift['id'], $user['id'], 'expense', 'Возврат перевода отправителю', $amt, $tid);

        $success = "Сумма возврата " . number_format($amt, 2) . " BYN успешно выплачена отправителю!";
        $transfer = null;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$page_title = "Выплата возврата перевода — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-danger text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-cash-coin me-2"></i>Выплата возврата перевода</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if($success): ?><div class="alert alert-success fw-bold rounded-4 p-4 shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger fw-bold rounded-4 p-4 shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div><?php endif; ?>

                <?php if (!$transfer): ?>
                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Поиск возврата</label>
                            <input type="text" name="q" class="form-control form-control-lg" required placeholder="Трек, Код возврата или Имя">
                        </div>
                        <button type="submit" name="search" class="btn btn-danger btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                            <i class="bi bi-search me-2"></i>Найти возврат
                        </button>
                    </form>
                <?php else: ?>
                    <div class="p-4 bg-light rounded-4 mb-4 border border-danger border-opacity-10 shadow-sm text-center">
                        <div class="text-muted small text-uppercase fw-bold mb-2">ПОЛУЧАТЕЛЬ ВОЗВРАТА (ОТПРАВИТЕЛЬ):</div>
                        <div class="h4 fw-bold mb-3"><?php echo e($transfer['sender_name'] ?: $transfer['sender_login']); ?></div>
                        <hr>
                        <div class="text-muted small text-uppercase fw-bold mb-1">СУММА К ВОЗВРАТУ:</div>
                        <div class="display-5 fw-extrabold text-danger"><?php echo number_format($transfer['amount'], 2); ?> BYN</div>
                        <div class="mt-2 badge bg-danger bg-opacity-10 text-danger fw-bold"><?php echo e($transfer['refund_code']); ?></div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="id" value="<?php echo $transfer['id']; ?>">
                        <button type="submit" name="issue" class="btn btn-danger btn-lg w-100 rounded-pill py-3 fw-bold shadow-lg text-uppercase">
                            <i class="bi bi-cash me-2"></i>Подтвердить выплату
                        </button>
                    </form>
                    <div class="text-center mt-3"><a href="transfer_refund_issue.php" class="btn btn-link text-muted">Сбросить поиск</a></div>
                <?php endif; ?>
                <div class="text-center mt-4"><a href="dashboard.php" class="btn btn-link text-muted">Назад в дашборд</a></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
