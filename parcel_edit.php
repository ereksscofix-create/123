<?php
// parcel_edit.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан");

$error = '';
$success = '';

// Тарифы и их множители
$rates = [
    'EK'  => ['name' => 'Эконом (EK)', 'rate' => 7],
    'EKP' => ['name' => 'Эконом Плюс (EKP)', 'rate' => 15],
    'ST'  => ['name' => 'Стандарт (ST)', 'rate' => 22],
    'EX'  => ['name' => 'Экспресс (EX)', 'rate' => 31],
    'QQ'  => ['name' => 'Квик (QQ)', 'rate' => 60],
    'N'   => ['name' => 'Ночной (N)', 'rate' => 3],
    'P'   => ['name' => 'Письмо (P)', 'rate' => 4],
];

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");

    // ПРОВЕРКА ПРАВ: Работник может всё, пользователь — только свои посылки
    if ($user['role'] !== 'worker' && (int)$parcel['sender_id'] !== (int)$user['id']) {
        die("У вас нет прав на редактирование этой посылки.");
    }
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $weight = (float)$_POST['weight'];
    $tariff_key = $_POST['tariff'] ?? 'ST';

    $multiplier = $rates[$tariff_key]['rate'] ?? 10;
    $cost = $weight * $multiplier;

    $cod = (float)$_POST['cod'];
    $declared_value = (float)$_POST['declared_value'];

    try {
        $stmt = $pdo->prepare("UPDATE parcels SET address = :addr, weight = :w, cost = :c, tariff = :t, cod = :cod, declared_value = :dv WHERE id = :id");
        $stmt->execute([
            'addr' => $address,
            'w' => $weight,
            'c' => $cost,
            't' => $tariff_key,
            'cod' => $cod,
            'dv' => $declared_value,
            'id' => $id
        ]);
        $success = "Данные обновлены! Стоимость: " . number_format($cost, 2) . " BYN";
        $parcel['address'] = $address;
        $parcel['weight'] = $weight;
        $parcel['cost'] = $cost;
        $parcel['tariff'] = $tariff_key;
        $parcel['cod'] = $cod;
        $parcel['declared_value'] = $declared_value;
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$page_title = "Редактировать посылку — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-warning text-dark p-4 border-0">
                <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Редактирование: <?php echo e($parcel['track_code']); ?></h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                        <div><?php echo $success; ?> <a href="dashboard.php" class="fw-bold ms-2">В дашборд</a></div>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Тариф</label>
                        <select name="tariff" class="form-select form-select-lg rounded-3" required>
                            <?php foreach ($rates as $key => $data): ?>
                                <option value="<?php echo $key; ?>" <?php echo $parcel['tariff'] === $key ? 'selected' : ''; ?>><?php echo e($data['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Вес (кг)</label>
                        <input type="number" name="weight" class="form-control form-control-lg rounded-3" step="0.001" required value="<?php echo e($parcel['weight']); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-muted small text-uppercase">Адрес доставки</label>
                        <textarea name="address" class="form-control rounded-3" rows="3" required><?php echo e($parcel['address']); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Наложенный платеж (BYN)</label>
                        <input type="number" name="cod" class="form-control form-control-lg rounded-3" step="0.01" value="<?php echo e($parcel['cod']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Объявл. ценность (BYN)</label>
                        <input type="number" name="declared_value" class="form-control form-control-lg rounded-3" step="0.01" value="<?php echo e($parcel['declared_value']); ?>">
                    </div>

                    <div class="col-12 mt-5 pt-3 border-top d-flex flex-wrap gap-3">
                        <button type="submit" class="btn btn-warning px-5 rounded-pill shadow-sm fw-bold flex-grow-1 py-3 text-uppercase">Сохранить изменения</button>
                        <a href="dashboard.php" class="btn btn-light px-5 rounded-pill border fw-bold flex-grow-1 py-3 text-uppercase">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
