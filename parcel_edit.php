<?php
// parcel_edit.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан");

$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = $_POST['address'];
    $weight = (float)$_POST['weight'];
    $cost = (float)$_POST['cost'];
    $tariff = $_POST['tariff'];

    try {
        $stmt = $pdo->prepare("UPDATE parcels SET address = :addr, weight = :w, cost = :c, tariff = :t WHERE id = :id");
        $stmt->execute([
            'addr' => $address,
            'w' => $weight,
            'c' => $cost,
            't' => $tariff,
            'id' => $id
        ]);
        $success = "Данные посылки обновлены!";
        // Обновляем данные в текущей переменной для отображения в форме
        $parcel['address'] = $address;
        $parcel['weight'] = $weight;
        $parcel['cost'] = $cost;
        $parcel['tariff'] = $tariff;
    } catch (PDOException $e) {
        $error = "Ошибка при обновлении: " . $e->getMessage();
    }
}

$page_title = "Редактировать посылку — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Редактирование посылки <?php echo e($parcel['track_code']); ?></h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?> <a href="dashboard.php">Вернуться в дашборд</a></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Тариф</label>
                            <select name="tariff" class="form-select">
                                <option value="Стандарт" <?php echo $parcel['tariff'] === 'Стандарт' ? 'selected' : ''; ?>>Стандарт</option>
                                <option value="Экспресс" <?php echo $parcel['tariff'] === 'Экспресс' ? 'selected' : ''; ?>>Экспресс</option>
                                <option value="Международный" <?php echo $parcel['tariff'] === 'Международный' ? 'selected' : ''; ?>>Международный</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Адрес доставки</label>
                            <textarea name="address" class="form-control" rows="2" required><?php echo e($parcel['address']); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Вес (кг)</label>
                            <input type="number" name="weight" class="form-control" step="0.01" required value="<?php echo e($parcel['weight']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Стоимость (BYN)</label>
                            <input type="number" name="cost" class="form-control" step="0.01" required value="<?php echo e($parcel['cost']); ?>">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-5">Сохранить изменения</button>
                        <a href="dashboard.php" class="btn btn-link text-muted">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
