<?php
// parcel_add.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Упрощенная логика добавления
    $track = 'EP' . rand(100000000, 999999999) . 'BY';
    $recipient_id = (int)$_POST['recipient_id'];
    $address = $_POST['address'];
    $weight = (float)$_POST['weight'];
    $cost = (float)$_POST['cost'];
    $tariff = $_POST['tariff'];

    try {
        $stmt = $pdo->prepare("INSERT INTO parcels (track_code, sender_id, recipient_id, address, weight, cost, tariff, created_at)
                               VALUES (:track, :sid, :rid, :addr, :w, :c, :t, NOW())");
        $stmt->execute([
            'track' => $track,
            'sid' => $user['id'],
            'rid' => $recipient_id,
            'addr' => $address,
            'w' => $weight,
            'c' => $cost,
            't' => $tariff
        ]);
        $success = "Посылка $track успешно оформлена!";
    } catch (PDOException $e) {
        $error = "Ошибка при добавлении: " . $e->getMessage();
    }
}

// Получаем список пользователей для выбора получателя (упрощенно)
$users = [];
try {
    $users = $pdo->query("SELECT id, name FROM users WHERE id != {$user['id']}")->fetchAll();
} catch (PDOException $e) {}

$page_title = "Оформить посылку — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Оформление нового отправления</h5>
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
                        <div class="col-md-6">
                            <label class="form-label">Получатель</label>
                            <select name="recipient_id" class="form-select" required>
                                <option value="">-- Выберите получателя --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo e($u['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Тариф</label>
                            <select name="tariff" class="form-select">
                                <option value="Стандарт">Стандарт</option>
                                <option value="Экспресс">Экспресс</option>
                                <option value="Международный">Международный</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Адрес доставки</label>
                            <textarea name="address" class="form-control" rows="2" required placeholder="Город, улица, дом, квартира..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Вес (кг)</label>
                            <input type="number" name="weight" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Стоимость (BYN)</label>
                            <input type="number" name="cost" class="form-control" step="0.01" required>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-5">Оформить</button>
                        <a href="dashboard.php" class="btn btn-link text-muted">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
