<?php
// parcel_add.php — Оформление посылки
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

    // Генерация трек-кода: Код тарифа + 9 случайных цифр + BY
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
        $error = "Пожалуйста, заполните все обязательные поля (ID отправителя, ID получателя и адрес).";
    } else {
        try {
            // Проверка существования пользователей
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :sid OR id = :rid");
            $stmt->execute(['sid' => $sender_id, 'rid' => $recipient_id]);
            $found_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array($sender_id, $found_ids) || !in_array($recipient_id, $found_ids)) {
                $error = "Один или оба ID пользователей не найдены в системе. Убедитесь, что ID " . $sender_id . " и " . $recipient_id . " существуют.";
            } else {
                // ВАЖНО: Убрали created_at из списка полей, так как в БД этой колонки может не быть
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

                // Добавляем начальный статус (также без created_at)
                $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, 'Оформлена')");
                $stmt->execute(['pid' => $parcel_id]);

                $success = "Посылка <strong>$track</strong> успешно оформлена!<br>Расчетная стоимость: <strong>" . number_format($cost, 2) . " BYN</strong>";
            }
        } catch (PDOException $e) {
            $error = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}

$page_title = "Оформить посылку — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-10 col-lg-7">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white p-4 border-0 rounded-top-4">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 p-3 rounded-circle me-4">
                        <i class="bi bi-box-seam-fill fs-2"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold">Новое отправление</h3>
                        <p class="mb-0 opacity-75 small">Введите данные посылки и участников</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-5">
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-5 p-4 rounded-4">
                        <i class="bi bi-check-circle-fill fs-2 me-3"></i>
                        <div>
                            <div class="fw-bold fs-5 mb-1">Готово!</div>
                            <?php echo $success; ?>
                            <div class="mt-3">
                                <a href="dashboard.php" class="btn btn-success rounded-pill px-4 me-2">В дашборд</a>
                                <a href="parcel_add.php" class="btn btn-outline-success rounded-pill px-4">Еще одна</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-5 p-4 rounded-4">
                        <i class="bi bi-exclamation-octagon-fill fs-2 me-3"></i>
                        <div>
                            <div class="fw-bold fs-5 mb-1">Ошибка</div>
                            <?php echo $error; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" id="parcelForm" class="row g-4">
                    <!-- Участники -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">ID Отправителя</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-up"></i></span>
                            <input type="number" name="sender_id" class="form-control form-control-lg border-start-0" value="<?php echo (int)$user['id']; ?>" required>
                        </div>
                        <div class="form-text x-small">Ваш текущий ID: <strong><?php echo (int)$user['id']; ?></strong></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">ID Получателя</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-down"></i></span>
                            <input type="number" name="recipient_id" class="form-control form-control-lg border-start-0" required placeholder="Введите ID">
                        </div>
                    </div>

                    <!-- Тариф и Вес -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Тарифный план</label>
                        <select name="tariff" id="tariffSelect" class="form-select form-select-lg" required>
                            <?php foreach ($rates as $key => $data): ?>
                                <option value="<?php echo $key; ?>" data-rate="<?php echo $data['rate']; ?>" <?php echo $key === 'ST' ? 'selected' : ''; ?>>
                                    <?php echo e($data['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Вес отправления (кг)</label>
                        <div class="input-group">
                            <input type="number" name="weight" id="weightInput" class="form-control form-control-lg" step="0.001" value="0.500" required>
                            <span class="input-group-text bg-light">КГ</span>
                        </div>
                    </div>

                    <!-- Калькулятор -->
                    <div class="col-12">
                        <div class="p-4 rounded-4 text-center" style="background: linear-gradient(45deg, #f8f9fa, #eef2f7); border: 2px dashed var(--primary);">
                            <div class="text-muted small fw-bold text-uppercase mb-2">Стоимость доставки составит</div>
                            <div class="h1 mb-0 text-primary fw-extrabold"><span id="totalCost">0.00</span> <span class="small fs-4">BYN</span></div>
                        </div>
                    </div>

                    <!-- Адрес -->
                    <div class="col-12">
                        <label class="form-label fw-bold small text-uppercase text-muted">Точный адрес доставки</label>
                        <textarea name="address" class="form-control border-primary border-opacity-25" rows="3" required placeholder="Город, улица, дом, подъезд, квартира..."></textarea>
                    </div>

                    <!-- Доп услуги -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Наложенный платеж</label>
                        <div class="input-group">
                            <input type="number" name="cod" class="form-control" step="0.01" value="0.00">
                            <span class="input-group-text bg-light">BYN</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-muted">Объявленная ценность</label>
                        <div class="input-group">
                            <input type="number" name="declared_value" class="form-control" step="0.01" value="0.00">
                            <span class="input-group-text bg-light">BYN</span>
                        </div>
                    </div>

                    <div class="col-12 mt-5">
                        <button type="submit" class="btn btn-primary btn-lg w-100 shadow-lg rounded-pill py-3 fw-bold text-uppercase">
                            <i class="bi bi-plus-lg me-2"></i>Сформировать посылку
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

    function calculate() {
        const rate = parseFloat(tariffSelect.options[tariffSelect.selectedIndex].getAttribute('data-rate'));
        const weight = parseFloat(weightInput.value) || 0;
        const cost = weight * rate;
        totalCost.textContent = cost.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    tariffSelect.addEventListener('change', calculate);
    weightInput.addEventListener('input', calculate);
    calculate();
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
