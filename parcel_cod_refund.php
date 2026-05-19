<?php
// parcel_cod_refund.php — Выплата возврата наложенного платежа получателю
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


$success = '';
$error = '';
$parcel = null;

if (isset($_GET['q']) && !isset($_POST['search'])) {
    $_POST['search'] = true;
    $_POST['q'] = $_GET['q'];
}

if (isset($_POST['search'])) {
    $q = trim($_POST['q'] ?? '');
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT p.*, r.name as recipient_name, r.login as recipient_login
                               FROM parcels p
                               JOIN users r ON p.recipient_id = r.id
                               WHERE (p.track_code = :q OR p.refund_code = :q OR r.name LIKE :lk OR r.login LIKE :lk)
                               AND p.refund_code IS NOT NULL AND p.refund_code != ''
                               AND p.is_cod_paid = 1 AND p.cod_refund_issued = 0
                               ORDER BY p.id DESC LIMIT 1");
        $stmt->execute(['q' => $q, 'lk' => "%$q%"]);
        $parcel = $stmt->fetch();
        if (!$parcel) $error = "Активный возврат наложенного платежа по вашему запросу не найден.";
    }
}

if (isset($_POST['issue']) && isset($_POST['parcel_id'])) {
    $pid = (int)$_POST['parcel_id'];
    $input_code = trim($_POST['refund_code'] ?? '');
    try {
        $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $pid]);
        $parcel = $stmt->fetch();

        if ($parcel && $parcel['is_cod_paid'] && !$parcel['cod_refund_issued']) {
            if ($parcel['refund_code'] !== $input_code) {
                $error = "Неверный код возврата наложенного платежа.";
            } else {
                $stmt = $pdo->prepare("UPDATE parcels SET cod_refund_issued = 1 WHERE id = :id");
                $stmt->execute(['id' => $pid]);

                // Логируем расход (Выплата из кассы)
            logTransaction($shift['id'], $user['id'], 'expense', 'Возврат нал.плат. получателю', $parcel['cod'], $pid);

            $status_text = "Выплачен возврат наложенного платежа получателю [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $pid, 'txt' => $status_text]);

                $success = "Сумма " . number_format($parcel['cod'], 2) . " BYN успешно возвращена получателю!";
                $parcel = null; // Очищаем для нового поиска
            }
        } else {
            $error = "Не удалось выполнить возврат. Возможно, он уже был выдан.";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$page_title = "Возврат нал.плат. — EHPST";
include __DIR__ . '/header.php';

// Проверка открытой смены
$shift = getOpenShift($user['id']);
if (!$shift) {
    echo "<div class='alert alert-danger mt-5 p-5 rounded-4 text-center shadow-lg'><h2 class='fw-bold'>🚨 ОШИБКА: СМЕНА НЕ ОТКРЫТА</h2><p class='fs-5 mt-3'>Для проведения финансовых операций необходимо сначала открыть рабочую смену.</p><a href='shift_manage.php' class='btn btn-light fw-bold mt-4 px-4 rounded-pill'>ПЕРЕЙТИ К УПРАВЛЕНИЮ СМЕНАМИ</a></div>";
    include __DIR__ . '/footer.php';
    exit;
}
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-danger text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-arrow-counterclockwise me-2"></i>Возврат наложенного платежа</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if($success): ?><div class="alert alert-success fw-bold rounded-4 p-4 shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger fw-bold rounded-4 p-4 shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div><?php endif; ?>

                <?php if (!$parcel): ?>
                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Поиск возврата</label>
                            <input type="text" name="q" class="form-control form-control-lg" required placeholder="Трек, Код возврата или Имя">
                            <div class="form-text">Введите код возврата (REF-XXXX-XXXX) или трек-код посылки.</div>
                        </div>
                        <button type="submit" name="search" class="btn btn-danger btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                            <i class="bi bi-search me-2"></i>Найти возврат
                        </button>
                    </form>
                <?php else: ?>
                    <div class="p-4 bg-light rounded-4 mb-4 border border-danger border-opacity-10 shadow-sm text-center">
                        <div class="text-muted small text-uppercase fw-bold mb-2">ПОЛУЧАТЕЛЬ ВОЗВРАТА:</div>
                        <div class="h4 fw-bold mb-3"><?php echo e($parcel['recipient_name'] ?: $parcel['recipient_login']); ?></div>
                        <hr>
                        <div class="text-muted small text-uppercase fw-bold mb-1">СУММА К ВОЗВРАТУ:</div>
                        <div class="display-5 fw-extrabold text-danger"><?php echo number_format($parcel['cod'], 2); ?> BYN</div>
                        <div class="mt-2 badge bg-danger bg-opacity-10 text-danger fw-bold"><?php echo e($parcel['refund_code']); ?></div>
                    </div>

                    <div class="alert alert-warning small border-0 shadow-sm mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Убедитесь, что получатель предъявил паспорт или предоставил верный секретный код возврата.
                    </div>

                    <form method="post">
                        <input type="hidden" name="parcel_id" value="<?php echo $parcel['id']; ?>">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Код возврата (REF-XXXX-XXXX)</label>
                            <input type="text" name="refund_code" class="form-control form-control-lg text-center" required placeholder="REF-XXXX-XXXX">
                        </div>
                        <button type="submit" name="issue" class="btn btn-danger btn-lg w-100 rounded-pill py-3 fw-bold shadow-lg text-uppercase">
                            <i class="bi bi-cash me-2"></i>Выплатить возврат
                        </button>
                    </form>
                    <div class="text-center mt-3"><a href="parcel_cod_refund.php" class="btn btn-link text-muted">Сбросить поиск</a></div>
                <?php endif; ?>
                <div class="text-center mt-4"><a href="dashboard.php" class="btn btn-link text-muted">Вернуться в дашборд</a></div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
