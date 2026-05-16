<?php
// parcel_pvz_receive.php — Автоматический прием посылки на полку ПВЗ
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
    if (!$parcel['pickup_point']) die("Для этой посылки не выбран пункт выдачи (ПВЗ).");
} catch (Exception $e) { die("Ошибка БД: " . $e->getMessage()); }

$success = false;
$shelf = 0;
$error = '';

if (isset($_POST['receive'])) {
    try {
        // ЛОГИКА ПОИСКА ПОЛКИ (1-6, макс 10 посылок на полку)
        $found_shelf = 0;
        for ($i = 1; $i <= 6; $i++) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE pickup_point = :pvz AND shelf = :shelf AND id NOT IN (SELECT parcel_id FROM parcel_status WHERE status_text LIKE 'Выдана%')");
            $stmt->execute(['pvz' => $parcel['pickup_point'], 'shelf' => $i]);
            $count = (int)$stmt->fetchColumn();

            if ($count < 10) {
                $found_shelf = $i;
                break;
            }
        }

        if ($found_shelf > 0) {
            $shelf = $found_shelf;
            $stmt = $pdo->prepare("UPDATE parcels SET shelf = :shelf WHERE id = :id");
            $stmt->execute(['shelf' => $shelf, 'id' => $id]);

            // Номер полки не пишем в публичный статус
            $status_text = "Принято в ПВЗ [" . $parcel['pickup_point'] . "] [" . date('d.m.Y H:i') . "] (Оператор: " . ($user['name'] ?: $user['login']) . ")";
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $id, 'txt' => $status_text]);

            $success = true;
        } else {
            $error = "Все полки (1-6) в этом ПВЗ заполнены! (Лимит 10 посылок на полку)";
        }
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
                        <div class="p-4 bg-light rounded-4 border border-2 border-primary mb-4">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Место хранения:</div>
                            <div class="display-4 fw-extrabold text-primary">ПОЛКА №<?php echo $shelf; ?></div>
                        </div>
                        <p class="text-muted">Пожалуйста, разместите посылку на указанную полку.</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3 rounded-pill px-5">Завершить</a>
                    </div>
                <?php else: ?>
                    <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                    <div class="mb-5 text-center">
                        <div class="text-muted small text-uppercase fw-bold">Посылка:</div>
                        <div class="h3 fw-bold text-primary"><?php echo e($parcel['track_code']); ?></div>
                        <div class="badge bg-light text-dark border mt-2">Пункт: <?php echo e($parcel['pickup_point']); ?></div>
                    </div>

                    <form method="post">
                        <div class="alert alert-info border-0 shadow-sm small mb-4">
                            Система автоматически подберет свободную полку (от 1 до 6) с учетом текущей загрузки ПВЗ.
                        </div>
                        <button type="submit" name="receive" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold text-uppercase shadow-sm">
                            <i class="bi bi-download me-2"></i>Назначить полку и принять
                        </button>
                        <a href="dashboard.php" class="btn btn-link w-100 mt-3 text-muted">Отмена</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
