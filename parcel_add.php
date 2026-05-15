<?php
// parcel_add.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Генерируем трек-код: EP + 9 цифр + BY
    $track = 'EP' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT) . 'BY';

    $recipient_id = (int)($_POST['recipient_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $weight = (float)($_POST['weight'] ?? 0);
    $cost = (float)($_POST['cost'] ?? 0);
    $tariff = $_POST['tariff'] ?? 'Стандарт';
    $cod = (float)($_POST['cod'] ?? 0);
    $declared_value = (float)($_POST['declared_value'] ?? 0);

    if ($recipient_id <= 0 || empty($address)) {
        $error = "Пожалуйста, выберите получателя и укажите адрес.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO parcels
                (track_code, sender_id, recipient_id, address, weight, cost, tariff, cod, declared_value, created_at)
                VALUES (:track, :sid, :rid, :addr, :w, :c, :t, :cod, :dv, NOW())");

            $stmt->execute([
                'track' => $track,
                'sid' => $user['id'],
                'rid' => $recipient_id,
                'addr' => $address,
                'w' => $weight,
                'c' => $cost,
                't' => $tariff,
                'cod' => $cod,
                'dv' => $declared_value
            ]);

            $parcel_id = $pdo->lastInsertId();

            // Добавляем начальный статус
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text, created_at) VALUES (:pid, 'Оформлена', NOW())");
            $stmt->execute(['pid' => $parcel_id]);

            $success = "Посылка <strong>$track</strong> успешно оформлена!";
        } catch (PDOException $e) {
            $error = "Ошибка БД: " . $e->getMessage();
        }
    }
}

$users = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, login FROM users WHERE id != :myid ORDER BY name ASC");
    $stmt->execute(['myid' => $user['id']]);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Не удалось загрузить список пользователей.";
}

$page_title = "Оформить посылку — EHPST";
$name = $user['name'] ?: $user['login'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3 border-0">
                <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Новое отправление</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4">
                        <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                        <div class="mt-2">
                            <a href="dashboard.php" class="btn btn-sm btn-success">В дашборд</a>
                            <a href="parcel_add.php" class="btn btn-sm btn-outline-success">Оформить еще одну</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="needs-validation">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Получатель</label>
                            <select name="recipient_id" class="form-select" required>
                                <option value="">-- Выберите из списка --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo e($u['name'] ?: $u['login']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Тариф</label>
                            <select name="tariff" class="form-select">
                                <option value="Стандарт">Стандарт</option>
                                <option value="Экспресс">Экспресс</option>
                                <option value="Международный">Международный</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Адрес доставки</label>
                            <textarea name="address" class="form-control" rows="2" required placeholder="Город, улица, дом, квартира..."></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Вес (кг)</label>
                            <input type="number" name="weight" class="form-control" step="0.001" value="0.100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Стоимость (BYN)</label>
                            <input type="number" name="cost" class="form-control" step="0.01" value="5.50" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Наложенный платеж (BYN)</label>
                            <input type="number" name="cod" class="form-control" step="0.01" value="0.00">
                            <div class="form-text small">Оставьте 0, если не требуется</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Объявленная ценность (BYN)</label>
                            <input type="number" name="declared_value" class="form-control" step="0.01" value="0.00">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">Создать отправление</button>
                        <a href="dashboard.php" class="btn btn-light px-4 py-2">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
