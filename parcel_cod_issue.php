<?php
// parcel_cod_issue.php — Выплата наложенного платежа отправителю
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$shift = getOpenShift($user['id']);
if (!$shift) die("Ошибка: Смена не открыта.");

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан.");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");
    if (!$parcel['is_cod_paid']) die("Наложенный платеж еще не оплачен получателем.");
    if ($parcel['is_cod_issued']) die("Наложенный платеж уже выплачен отправителю.");

    if (isset($_POST['issue'])) {
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

} catch (Exception $e) { die("Ошибка БД: " . $e->getMessage()); }

$page_title = "Выплата нал.плат. " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 text-center">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white p-4">
                <h4 class="mb-0 fw-bold">Выплата отправителю</h4>
            </div>
            <div class="card-body p-5">
                <div class="mb-4">
                    <div class="text-muted small">СУММА К ВЫПЛАТЕ ЗА ПОСЫЛКУ <?php echo $parcel['track_code']; ?>:</div>
                    <div class="display-5 fw-extrabold text-primary"><?php echo number_format($parcel['cod'], 2); ?> BYN</div>
                </div>
                <div class="alert alert-info small">Убедитесь, что личность получателя (отправителя посылки) проверена.</div>
                <form method="post">
                    <button type="submit" name="issue" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold">ПОДТВЕРДИТЬ ВЫПЛАТУ</button>
                    <a href="dashboard.php" class="btn btn-link w-100 mt-2 text-muted">Отмена</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
