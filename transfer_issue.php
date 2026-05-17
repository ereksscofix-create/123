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

if (isset($_POST['search'])) {
    $code = trim($_POST['transfer_code'] ?? '');
    $secret = trim($_POST['secret_code'] ?? '');
    $search_query = trim($_POST['search_query'] ?? '');

    if ($code !== '' && $secret !== '') {
        $stmt = $pdo->prepare("SELECT * FROM money_transfers WHERE transfer_code = :code AND secret_code = :secret AND status = 'paid' LIMIT 1");
        $stmt->execute(['code' => $code, 'secret' => $secret]);
        $transfer = $stmt->fetch();
    } elseif ($search_query !== '') {
        // Поиск по имени или логину получателя
        $stmt = $pdo->prepare("SELECT mt.* FROM money_transfers mt
                               JOIN users u ON mt.recipient_id = u.id
                               WHERE (u.name LIKE :q OR u.login LIKE :q) AND mt.status = 'paid'
                               ORDER BY mt.created_at DESC LIMIT 1");
        $stmt->execute(['q' => "%$search_query%"]);
        $transfer = $stmt->fetch();
    }

    if (!$transfer) $error = "Перевод не найден, неверный код или уже выдан.";
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
                <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <?php if (!$transfer): ?>
                <form method="post">
                    <div class="p-3 bg-light rounded-4 mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-qr-code-scan me-2"></i>По коду и секрету</h6>
                        <div class="mb-3">
                            <input type="text" name="transfer_code" class="form-control" placeholder="Номер TR000000BY">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="secret_code" class="form-control" placeholder="Секретный код XXXX">
                        </div>
                    </div>

                    <div class="text-center my-3 text-muted small">— ИЛИ —</div>

                    <div class="p-3 bg-light rounded-4 mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i>По имени или логину</h6>
                        <input type="text" name="search_query" class="form-control" placeholder="Имя или логин получателя">
                    </div>

                    <button type="submit" name="search" class="btn btn-success btn-lg w-100 rounded-pill shadow-sm py-3 fw-bold">
                        <i class="bi bi-search me-2"></i>НАЙТИ ПЕРЕВОД
                    </button>
                </form>
                <?php else: ?>
                    <div class="p-4 bg-light rounded-4 text-center mb-4">
                        <div class="text-muted small">СУММА К ВЫДАЧЕ:</div>
                        <div class="display-5 fw-bold text-success"><?php echo number_format($transfer['amount'], 2); ?> BYN</div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="id" value="<?php echo $transfer['id']; ?>">
                        <button type="submit" name="issue" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold">ПОДТВЕРДИТЬ ВЫДАЧУ</button>
                    </form>
                <?php endif; ?>
                <div class="text-center mt-3"><a href="dashboard.php" class="btn btn-link text-muted">Назад</a></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
