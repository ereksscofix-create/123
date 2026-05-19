<?php
// parcel_cod_issue.php — Выплата наложенного платежа отправителю
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

if (isset($_GET['debug'])) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

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
$parcel = null;
$error = '';

if (isset($_POST['search'])) {
    $q = trim($_POST['q'] ?? '');
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT p.*, u.name as sender_name, u.login as sender_login
                               FROM parcels p
                               JOIN users u ON p.sender_id = u.id
                               WHERE (p.track_code = :q OR u.name LIKE :lk OR u.login LIKE :lk)
                               AND p.is_cod_paid = 1 AND p.is_cod_issued = 0
                               ORDER BY p.id DESC LIMIT 1");
        $stmt->execute(['q' => $q, 'lk' => "%$q%"]);
        $parcel = $stmt->fetch();
        if (!$parcel) $error = "Активный наложенный платеж по вашему запросу не найден.";
    }
}

try {
    if ($id && !$parcel) {
        $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $parcel = $stmt->fetch();
    }

    if ($parcel) {
        if (!$parcel['is_cod_paid']) die("Наложенный платеж еще не оплачен получателем.");
        if ($parcel['is_cod_issued']) die("Наложенный платеж уже выплачен отправителю.");
    }

    if (isset($_POST['issue']) && isset($_POST['parcel_id'])) {
        $id = (int)$_POST['parcel_id'];
        $input_code = trim($_POST['payout_code'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $parcel = $stmt->fetch();

        if ($parcel['cod_payout_code'] !== $input_code) {
            $error = "Неверный код для получения выплаты.";
        } else {
            $stmt = $pdo->prepare("UPDATE parcels SET is_cod_issued = 1 WHERE id = :id");
            $stmt->execute(['id' => $id]);

            // Логируем расход (Выплата из кассы)
        logTransaction($shift['id'], $user['id'], 'expense', 'Выплата нал.плат. отправителю', $parcel['cod'], $id);

        $status_text = "Выплачен наложенный платеж отправителю [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

            echo "<script>alert('Выплата успешно оформлена!'); window.location.href='dashboard.php';</script>";
            exit;
        }
    }

} catch (Exception $e) { die("Ошибка БД: " . $e->getMessage()); }

$page_title = "Выплата нал.плат. " . ($parcel['track_code'] ?? '');
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 text-center">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white p-4">
                <h4 class="mb-0 fw-bold">Выплата отправителю</h4>
            </div>
            <div class="card-body p-5">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <?php if (!$parcel): ?>
                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Поиск отправителя или трека</label>
                            <input type="text" name="q" class="form-control form-control-lg" required placeholder="Трек, Имя или Логин">
                        </div>
                        <button type="submit" name="search" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold">НАЙТИ ПЛАТЕЖ</button>
                    </form>
                <?php else: ?>
                    <div class="mb-4">
                        <div class="text-muted small text-uppercase fw-bold">Получатель выплаты (Отправитель посылки):</div>
                        <div class="h4 fw-bold mt-1"><?php echo e($parcel['sender_name'] ?? $parcel['sender_login'] ?? '---'); ?></div>
                        <hr>
                        <div class="text-muted small">СУММА К ВЫПЛАТЕ ЗА ПОСЫЛКУ <?php echo $parcel['track_code']; ?>:</div>
                        <div class="display-5 fw-extrabold text-primary"><?php echo number_format($parcel['cod'], 2); ?> BYN</div>
                    </div>
                    <div class="alert alert-info small">Убедитесь, что личность получателя проверена.</div>
                    <form method="post">
                        <input type="hidden" name="parcel_id" value="<?php echo $parcel['id']; ?>">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Код выплаты (6 цифр)</label>
                            <input type="text" name="payout_code" class="form-control form-control-lg text-center" required placeholder="XXXXXX" maxlength="6">
                        </div>
                        <button type="submit" name="issue" class="btn btn-success btn-lg rounded-pill px-5 py-3 fw-bold w-100">ПОДТВЕРДИТЬ ВЫПЛАТУ</button>
                    </form>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-link w-100 mt-3 text-muted">Назад</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
