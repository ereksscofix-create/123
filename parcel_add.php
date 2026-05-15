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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tariff_key = $_POST['tariff'] ?? 'ST';

    // Генерация трек-кода: Код тарифа + 9 цифр + BY
    $track = $tariff_key . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT) . 'BY';

    $sender_id = (int)($_POST['sender_id'] ?? 0);
    $recipient_id = (int)($_POST['recipient_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $weight = (float)($_POST['weight'] ?? 0);

    // Расчет стоимости
    $multiplier = $rates[$tariff_key]['rate'] ?? 10;
    $cost = $weight * $multiplier;

    $cod = (float)($_POST['cod'] ?? 0);
    $declared_value = (float)($_POST['declared_value'] ?? 0);

    if ($sender_id <= 0 || $recipient_id <= 0 || empty($address)) {
        $error = "Пожалуйста, укажите ID отправителя, ID получателя и адрес.";
    } else {
        try {
            // Проверка существования пользователей
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id IN (:sid, :rid)");
            $stmt->execute(['sid' => $sender_id, 'rid' => $recipient_id]);
            $count = (int)$stmt->fetchColumn();

            // Если ID одинаковые, count будет 1, но нам нужно проверить оба (или один и тот же дважды)
            // Упрощенная проверка:
            if ($count < 1) {
                 $error = "Указанные ID пользователей не найдены.";
            } else {
                // Удаляем created_at из запроса, так как колонка не найдена (вероятно, заполняется автоматически или называется иначе)
                $stmt = $pdo->prepare("INSERT INTO parcels
                    (track_code, sender_id, recipient_id, address, weight, cost, tariff, cod, declared_value)
                    VALUES (:track, :sid, :rid, :addr, :w, :c, :t, :cod, :dv)");

                $stmt->execute([
                    'track' => $track,
                    'sid' => $sender_id,
                    'rid' => $recipient_id,
                    'addr' => $address,
                    'w' => $weight,
                    'c' => $cost,
                    't' => $tariff_key,
                    'cod' => $cod,
                    'dv' => $declared_value
                ]);

                $parcel_id = $pdo->lastInsertId();

                // В parcel_status тоже убираем created_at
                $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, 'Оформлена')");
                $stmt->execute(['pid' => $parcel_id]);

                $success = "Посылка <strong>$track</strong> успешно оформлена! Стоимость: <strong>" . number_format($cost, 2) . " BYN</strong>";
            }
        } catch (PDOException $e) {
            $error = "Ошибка БД: " . $e->getMessage();
        }
    }
}

$page_title = "Оформить посылку — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-lg border-0 overflow-hidden mt-4">
            <div class="card-header bg-primary text-white p-4 border-0">
                <div class="d-flex align-items-center">
                    <i class="bi bi-box-seam-fill fs-2 me-3"></i>
                    <div>
                        <h4 class="mb-0 fw-bold">Оформление отправления</h4>
                        <p class="mb-0 small opacity-75">Введите ID участников и параметры посылки</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-5 bg-white">
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <?php echo $success; ?>
                            <div class="mt-2">
                                <a href="dashboard.php" class="btn btn-sm btn-success rounded-pill px-3">В дашборд</a>
                                <a href="parcel_add.php" class="btn btn-sm btn-outline-success rounded-pill px-3">Новая посылка</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
                        <i class="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" id="parcelForm" class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ID Отправителя</label>
                        <input type="number" name="sender_id" class="form-control form-control-lg" value="<?php echo (int)$user['id']; ?>" required>
                        <div class="form-text x-small text-primary">Ваш ID: <?php echo (int)$user['id']; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ID Получателя</label>
                        <input type="number" name="recipient_id" class="form-control form-control-lg" required placeholder="Введите число">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Тариф</label>
                        <select name="tariff" id="tariffSelect" class="form-select form-select-lg" required>
                            <?php foreach ($rates as $key => $data): ?>
                                <option value="<?php echo $key; ?>" data-rate="<?php echo $data['rate']; ?>" <?php echo $key === 'ST' ? 'selected' : ''; ?>>
                                    <?php echo e($data['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Вес (кг)</label>
                        <input type="number" name="weight" id="weightInput" class="form-control form-control-lg" step="0.001" value="0.500" required>
                    </div>

                    <div class="col-12">
                        <div class="bg-light p-4 rounded-4 text-center border-2 border-dashed border-primary">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Расчетная стоимость</div>
                            <div class="h1 mb-0 text-primary fw-extrabold"><span id="totalCost">0.00</span> <span class="small fs-5">BYN</span></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Адрес доставки</label>
                        <textarea name="address" class="form-control" rows="3" required placeholder="Город, улица, дом, корпус, кв..."></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Наложенный платеж (BYN)</label>
                        <input type="number" name="cod" class="form-control bg-light" step="0.01" value="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Объявленная ценность (BYN)</label>
                        <input type="number" name="declared_value" class="form-control bg-light" step="0.01" value="0.00">
                    </div>

                    <div class="col-12 mt-5">
                        <button type="submit" class="btn btn-primary btn-lg w-100 shadow rounded-pill py-3 fw-bold">
                            <i class="bi bi-plus-lg me-2"></i>Создать отправление
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tariffSelect = document.getElementById('tariffSelect');
    const weightInput = document.getElementById('weightInput');
    const totalCost = document.getElementById('totalCost');

    function updateCost() {
        const rate = parseFloat(tariffSelect.options[tariffSelect.selectedIndex].getAttribute('data-rate'));
        const weight = parseFloat(weightInput.value) || 0;
        const cost = weight * rate;
        totalCost.textContent = cost.toFixed(2);
    }

    tariffSelect.addEventListener('change', updateCost);
    weightInput.addEventListener('input', updateCost);
    updateCost();
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
