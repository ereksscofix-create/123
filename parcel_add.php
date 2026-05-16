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

    // Генерация трек-кода
    $track = $tariff_key . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT) . 'BY';

    $sender_id = (int)($_POST['sender_id'] ?? 0);
    $recipient_id = (int)($_POST['recipient_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $weight = (float)($_POST['weight'] ?? 0);

    $multiplier = $rates[$tariff_key]['rate'] ?? 10;
    $cost = $weight * $multiplier;

    $cod = (float)($_POST['cod'] ?? 0);
    $declared_value = (float)($_POST['declared_value'] ?? 0);

    if ($sender_id <= 0 || $recipient_id <= 0 || empty($address)) {
        $error = "Пожалуйста, заполните все обязательные поля.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :sid OR id = :rid");
            $stmt->execute(['sid' => $sender_id, 'rid' => $recipient_id]);
            $found_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array($sender_id, $found_ids) || !in_array($recipient_id, $found_ids)) {
                $error = "Один или оба ID пользователей не найдены.";
            } else {
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
                // Убираем created_at, так как колонки может не быть. Время добавим в текст для истории.
                $status_text = "Оформлена [" . date('d.m.Y H:i') . "]";
                $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
                $stmt->execute(['pid' => $parcel_id, 'txt' => $status_text]);

                $success = "Посылка <strong>$track</strong> успешно оформлена!<br>Стоимость: <strong>" . number_format($cost, 2) . " BYN</strong>";
            }
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

$page_title = "Оформить посылку — EHPST";
include __DIR__ . '/header.php';
?>

<style>
.btn-create { background: #6f42c1; color: #fff; border: none; transition: 0.3s; }
.btn-create:hover { background: #59359a; color: #fff; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(111, 66, 193, 0.3); }
</style>

<div class="row justify-content-center py-4">
    <div class="col-md-10 col-lg-7">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-primary text-white p-3 p-md-4 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 p-2 p-md-3 rounded-circle me-2 me-md-3"><i class="bi bi-box-seam-fill fs-3 fs-md-2"></i></div>
                    <div>
                        <h4 class="mb-0 fw-bold fs-5 fs-md-4">Новое отправление</h4>
                        <p class="mb-0 opacity-75 small d-none d-md-block">Заполните данные для создания трек-кода</p>
                    </div>
                </div>
            </div>
            <div class="card-body p-3 p-md-5">
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-5 p-4 rounded-4">
                        <i class="bi bi-check-circle-fill fs-2 me-3 text-success"></i>
                        <div>
                            <?php echo $success; ?>
                            <div class="mt-3">
                                <a href="dashboard.php" class="btn btn-success rounded-pill px-4 me-2">В дашборд</a>
                                <a href="parcel_add.php" class="btn btn-outline-success rounded-pill px-4">Создать еще</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 shadow-sm p-4 rounded-4 mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" id="parcelForm" class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">ID Отправителя</label>
                        <input type="number" name="sender_id" class="form-control form-control-lg rounded-3" value="<?php echo (int)$user['id']; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">ID Получателя</label>
                        <input type="number" name="recipient_id" class="form-control form-control-lg rounded-3" required placeholder="Введите ID">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Тариф</label>
                        <select name="tariff" id="tariffSelect" class="form-select form-select-lg rounded-3" required>
                            <?php foreach ($rates as $key => $data): ?>
                                <option value="<?php echo $key; ?>" data-rate="<?php echo $data['rate']; ?>" <?php echo $key === 'ST' ? 'selected' : ''; ?>><?php echo e($data['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Вес (кг)</label>
                        <input type="number" name="weight" id="weightInput" class="form-control form-control-lg rounded-3" step="0.001" value="0.500" required>
                    </div>

                    <div class="col-12">
                        <div class="p-4 rounded-4 text-center bg-light border border-2 border-dashed border-primary">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Итоговая стоимость</div>
                            <div class="h2 mb-0 text-primary fw-extrabold"><span id="totalCost">0.00</span> <span class="fs-5">BYN</span></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-muted small text-uppercase">Адрес доставки</label>
                        <textarea name="address" class="form-control rounded-3" rows="2" required placeholder="Полный адрес..."></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Наложенный платеж</label>
                        <input type="number" name="cod" class="form-control rounded-3" step="0.01" value="0.00">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Объявл. ценность</label>
                        <input type="number" name="declared_value" class="form-control rounded-3" step="0.01" value="0.00">
                    </div>

                    <div class="col-12 mt-3 mt-md-5">
                        <button type="submit" class="btn btn-create btn-lg w-100 rounded-pill py-2 py-md-3 fw-extrabold text-uppercase">
                            <i class="bi bi-plus-lg me-2"></i>Сформировать
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
