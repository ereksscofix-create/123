<?php
// transfer_issue.php — Выплата денежного перевода
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
$transfers = [];

// Отладка белого экрана
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

if (isset($_POST['search'])) {
    try {
        $code = trim($_POST['transfer_code'] ?? '');
        $secret = trim($_POST['secret_code'] ?? '');
        $search_query = trim($_POST['search_query'] ?? '');

        if ($code !== '' && $secret !== '') {
            $stmt = $pdo->prepare("SELECT mt.*, u.name as r_name, u.login as r_login
                                   FROM money_transfers mt
                                   JOIN users u ON mt.recipient_id = u.id
                                   WHERE mt.transfer_code = :code AND mt.secret_code = :secret AND mt.status = 'paid' LIMIT 1");
            $stmt->execute(['code' => $code, 'secret' => $secret]);
            $transfer = $stmt->fetch();
            if (!$transfer) $error = "Перевод не найден или неверный секретный код.";
        } elseif ($search_query !== '') {
            // Поиск по имени или логину получателя — может быть несколько
            $stmt = $pdo->prepare("SELECT mt.*, u.name as r_name, u.login as r_login
                                   FROM money_transfers mt
                                   JOIN users u ON mt.recipient_id = u.id
                                   WHERE (u.name LIKE :q OR u.login LIKE :q) AND mt.status = 'paid'
                                   ORDER BY mt.created_at DESC");
            $stmt->execute(['q' => "%$search_query%"]);
            $transfers = $stmt->fetchAll();
            if (!$transfers) $error = "Активных переводов для данного пользователя не найдено.";
        } else {
            $error = "Введите данные для поиска.";
        }
    } catch (Exception $e) {
        $error = "Ошибка поиска: " . $e->getMessage();
    }
}

if (isset($_POST['issue']) && isset($_POST['id'])) {
    $tid = (int)$_POST['id'];
    try {
        $stmt = $pdo->prepare("SELECT amount FROM money_transfers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $tid]);
        $amt = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE money_transfers SET status = 'issued' WHERE id = :id");
        $stmt->execute(['id' => $tid]);

        // Логируем расход (Выплата из кассы)
        logTransaction($shift['id'], $user['id'], 'expense', 'Выплата перевода', $amt, $tid);

        $success = "Денежные средства в размере $amt BYN успешно выданы!";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$page_title = "Выплатить перевод — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-success text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2"></i>Выплата перевода</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if($success): ?><div class="alert alert-success fw-bold rounded-4 p-4 shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger fw-bold rounded-4 p-4 shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div><?php endif; ?>

                <?php if (!$transfer && empty($transfers)): ?>
                <form method="post">
                    <div class="p-3 bg-light rounded-4 mb-4 border border-primary border-opacity-10">
                        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-qr-code-scan me-2"></i>По коду и секрету</h6>
                        <div class="mb-3">
                            <input type="text" name="transfer_code" class="form-control form-control-lg rounded-3" placeholder="Номер TR000000BY">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="secret_code" class="form-control form-control-lg rounded-3" placeholder="Секретный код XXXX">
                        </div>
                    </div>

                    <div class="text-center my-3 text-muted small fw-bold">— ИЛИ —</div>

                    <div class="p-3 bg-light rounded-4 mb-4 border border-primary border-opacity-10">
                        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-person-badge me-2"></i>По имени или логину</h6>
                        <input type="text" name="search_query" class="form-control form-control-lg rounded-3" placeholder="Имя или логин получателя">
                    </div>

                    <button type="submit" name="search" class="btn btn-success btn-lg w-100 rounded-pill shadow-sm py-3 fw-bold text-uppercase">
                        <i class="bi bi-search me-2"></i>Найти перевод
                    </button>
                </form>
                <?php elseif (!empty($transfers)): ?>
                    <h6 class="fw-bold mb-3">Найдено переводов: <?php echo count($transfers); ?></h6>
                    <?php foreach ($transfers as $t): ?>
                        <div class="card border-0 bg-light rounded-4 mb-3 p-3 shadow-sm border-start border-success border-5">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold h5 mb-1 text-success"><?php echo number_format($t['amount'], 2); ?> BYN</div>
                                    <div class="small text-muted">Получатель: <b><?php echo e($t['r_name'] ?: $t['r_login']); ?></b></div>
                                    <div class="x-small text-muted">Дата: <?php echo date('d.m.Y H:i', strtotime($t['created_at'])); ?></div>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" name="issue" class="btn btn-success rounded-pill fw-bold">ВЫДАТЬ</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-4"><a href="transfer_issue.php" class="btn btn-outline-secondary rounded-pill px-4">Сбросить поиск</a></div>
                <?php else: ?>
                    <div class="p-4 bg-light rounded-4 text-center mb-4 border border-success border-opacity-25 shadow-sm">
                        <div class="text-muted small text-uppercase fw-bold mb-2">ПОЛУЧАТЕЛЬ: <?php echo e($transfer['r_name'] ?: $transfer['r_login']); ?></div>
                        <div class="text-muted small text-uppercase">СУММА К ВЫДАЧЕ:</div>
                        <div class="display-4 fw-extrabold text-success"><?php echo number_format($transfer['amount'], 2); ?> BYN</div>
                        <div class="mt-2 badge bg-success bg-opacity-10 text-success fw-bold"><?php echo e($transfer['transfer_code']); ?></div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="id" value="<?php echo $transfer['id']; ?>">
                        <button type="submit" name="issue" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold shadow-lg text-uppercase">
                            <i class="bi bi-cash-stack me-2"></i>Подтвердить выдачу
                        </button>
                    </form>
                    <div class="text-center mt-4"><a href="transfer_issue.php" class="btn btn-outline-secondary rounded-pill px-4">Отмена</a></div>
                <?php endif; ?>
                <div class="text-center mt-3"><a href="dashboard.php" class="btn btn-link text-muted">Назад</a></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
