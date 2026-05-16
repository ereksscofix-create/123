<?php
// parcel_pvz_receive.php — Прием посылки в ПВЗ (Назначение полки)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID посылки не указан.");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена.");
} catch (Exception $e) { die("Ошибка БД: " . $e->getMessage()); }

$success = false;
$shelf = 0;

if (isset($_POST['receive'])) {
    $shelf = (int)$_POST['shelf'];
    if ($shelf < 1 || $shelf > 6) die("Некорректный номер полки.");

    try {
        $stmt = $pdo->prepare("UPDATE parcels SET shelf = :shelf WHERE id = :id");
        $stmt->execute(['shelf' => $shelf, 'id' => $id]);

        $status_text = "Принято в ПВЗ [" . $parcel['pickup_point'] . "]. Полка: " . $shelf . " [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $id, 'txt' => $status_text]);

        $success = true;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$page_title = "Прием в ПВЗ " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i>Прием на склад ПВЗ</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($success): ?>
                    <div class="text-center">
                        <div class="display-1 text-success mb-3"><i class="bi bi-check-circle"></i></div>
                        <h3>Посылка принята!</h3>
                        <p class="h4">Положите посылку на полку: <span class="badge bg-primary px-4 py-2"><?php echo $shelf; ?></span></p>
                        <a href="dashboard.php" class="btn btn-light mt-4 rounded-pill">В дашборд</a>
                    </div>
                <?php else: ?>
                    <div class="mb-4 text-center">
                        <div class="text-muted small text-uppercase fw-bold">Посылка:</div>
                        <div class="h3 fw-bold text-primary"><?php echo e($parcel['track_code']); ?></div>
                        <div class="badge bg-light text-dark border mt-2">Пункт: <?php echo e($parcel['pickup_point']); ?></div>
                    </div>

                    <form method="post">
                        <label class="form-label fw-bold text-muted small text-uppercase">Выберите полку для хранения (1-6)</label>
                        <div class="d-flex justify-content-between gap-2 mb-4">
                            <?php for($i=1; $i<=6; $i++): ?>
                                <input type="radio" class="btn-check" name="shelf" id="shelf<?php echo $i; ?>" value="<?php echo $i; ?>" required <?php echo $i===1 ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary flex-grow-1 py-3 fw-bold fs-4" for="shelf<?php echo $i; ?>"><?php echo $i; ?></label>
                            <?php endfor; ?>
                        </div>

                        <button type="submit" name="receive" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                            <i class="bi bi-download me-2"></i>Принять посылку
                        </button>
                        <a href="dashboard.php" class="btn btn-link w-100 mt-3 text-muted">Отмена</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
