<?php
// parcel_cod_repay.php — Прием возврата наложенного платежа от отправителя
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$shift = getOpenShift($user['id']);
if (!$shift) {
    $page_title = "Ошибка — EHPST";
    include __DIR__ . '/header.php';
    echo "<div class='alert alert-danger mt-5 p-5 rounded-4 text-center shadow-lg'><h2 class='fw-bold'>🚨 ОШИБКА: СМЕНА НЕ ОТКРЫТА</h2><p class='fs-5 mt-3'>Для проведения финансовых операций необходимо сначала открыть рабочую смену.</p><a href='shift_manage.php' class='btn btn-light fw-bold mt-4 px-4 rounded-pill'>ПЕРЕЙТИ К УПРАВЛЕНИЮ СМЕНАМИ</a></div>";
    include __DIR__ . '/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан.");

try {
    $stmt = $pdo->prepare("SELECT p.*, s.name as s_name, s.login as s_login
                           FROM parcels p
                           LEFT JOIN users s ON p.sender_id = s.id
                           WHERE p.id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");
    if (!$parcel['cod_return_required']) die("Для этой посылки возврат наложенного платежа не требуется.");
} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$error = '';
if (isset($_POST['repay'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) die("CSRF validation failed.");

    try {
        $stmt = $pdo->prepare("UPDATE parcels SET cod_return_required = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        logTransaction($shift['id'], $user['id'], 'income', 'Возврат нал.плат. в кассу (от отправителя)', $parcel['cod'], $id);

        $status_text = "Принят возврат наложенного платежа в кассу [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        notifyUser($parcel['sender_id'], "Вы успешно вернули наложенный платеж за посылку {$parcel['track_code']}. Теперь вы можете забрать возврат.");

        header("Location: dashboard.php");
        exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$page_title = "Возврат нал.плат. в кассу";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-danger text-white p-4">
                <h4 class="mb-0 fw-bold">Прием возврата денег</h4>
            </div>
            <div class="card-body p-5 text-center">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <div class="mb-4">
                    <div class="text-muted small text-uppercase fw-bold">Клиент (Отправитель посылки):</div>
                    <div class="h4 fw-bold mt-1"><?php echo e($parcel['s_name'] ?: $parcel['s_login']); ?></div>
                    <hr>
                    <div class="text-muted small">СУММА К ВОЗВРАТУ В КАССУ:</div>
                    <div class="display-4 fw-extrabold text-danger"><?php echo number_format($parcel['cod'], 2); ?> BYN</div>
                </div>

                <form method="post">
                    <?php echo csrfInput(); ?>
                    <button type="submit" name="repay" class="btn btn-danger btn-lg rounded-pill px-5 py-3 fw-bold w-100">ПОДТВЕРДИТЬ ПОЛУЧЕНИЕ ДЕНЕГ</button>
                </form>
                <a href="dashboard.php" class="btn btn-link w-100 mt-3 text-muted">Назад</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
