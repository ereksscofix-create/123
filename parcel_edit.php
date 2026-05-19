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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) die("CSRF validation failed.");
    $sender_address = trim($_POST['sender_address'] ?? '');
    $sender_pvz = trim($_POST['sender_pvz'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pickup_point = trim($_POST['pickup_point'] ?? '');
    $weight = (float)$_POST['weight'];
    $tariff_key = $_POST['tariff'] ?? 'ST';
    $inventory = trim($_POST['inventory'] ?? '');
    $delivery_partner = trim($_POST['delivery_partner'] ?? '');

    $multiplier = $rates[$tariff_key]['rate'] ?? 10;

    // Специальная обработка для Марок (N и P)
    if ($tariff_key === 'N' || $tariff_key === 'P') {
        $weight = 1.0;
        $cod = 0.0;
        $declared_value = 0.0;
        $inventory = '';
        $base_cost = $multiplier; // Цена за штуку
        $cod_fee = 0;
        $dv_fee = 0;
        $inv_fee = 0;
    } else {
        $base_cost = $weight * $multiplier;
        $cod = (float)$_POST['cod'];
        $declared_value = (float)$_POST['declared_value'];
        $cod_fee = $cod * 0.015;
        $dv_fee = $declared_value * 0.017;
        $inv_fee = ($inventory !== '') ? $base_cost * 0.02 : 0;
    }

    $cost = $base_cost + $cod_fee + $dv_fee + $inv_fee;

    // Платное редактирование (4% комиссия для пользователей)
    $edit_fee = (float)($parcel['edit_fee'] ?? 0);
    if ($user['role'] !== 'worker') {
        $edit_fee += $cost * 0.04;
    }
    $total_cost = $cost + $edit_fee;

    try {
        try {
    $recipient_id = !empty($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
    $recipient_name_ext = trim($_POST['recipient_name_ext'] ?? '');

            $stmt = $pdo->prepare("UPDATE parcels SET
                sender_address = :saddr, sender_pvz = :spvz, address = :addr, pickup_point = :pvz,
                weight = :w, cost = :c, base_cost = :bc, cod_fee = :cf, dv_fee = :df, inv_fee = :if,
                tariff = :t, cod = :cod, declared_value = :dv, inventory = :inv,
        edit_fee = :ef, delivery_partner = :dp, is_paid = 0, recipient_id = :rid, recipient_name_ext = :rname
                WHERE id = :id");
            $stmt->execute([
                'saddr' => $sender_address, 'spvz' => $sender_pvz, 'addr' => $address, 'pvz' => $pickup_point,
                'w' => $weight, 'c' => $total_cost, 'bc' => $base_cost, 'cf' => $cod_fee, 'df' => $dv_fee, 'if' => $inv_fee,
                't' => $tariff_key, 'cod' => $cod, 'dv' => $declared_value, 'inv' => $inventory, 'id' => $id,
        'ef' => $edit_fee, 'dp' => $delivery_partner, 'rid' => $recipient_id, 'rname' => $recipient_name_ext
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'sender_address') !== false) {
                $stmt = $pdo->prepare("UPDATE parcels SET address = :addr, weight = :w, cost = :c, tariff = :t, cod = :cod, declared_value = :dv WHERE id = :id");
                $stmt->execute([
                    'addr' => $address, 'w' => $weight, 'c' => $cost,
                    't' => $tariff_key, 'cod' => $cod, 'dv' => $declared_value, 'id' => $id
                ]);
                $success_warning = " (Внимание: Адрес отправителя не изменен, запустите <a href='db_fix.php'>db_fix.php</a>)";
            } else { throw $e; }
        }
        $success = "Данные обновлены! Стоимость: " . number_format($cost, 2) . " BYN" . ($success_warning ?? '');

        // Если изменения повлекли необходимость оплаты, перенаправляем (только для пользователей)
        if ($user['role'] !== 'worker') {
            header("Location: parcel_pay.php?id=$id&edit=1");
            exit;
        }

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
                    <?php echo csrfInput(); ?>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Тариф</label>
                        <select name="tariff" id="tariffSelect" class="form-select form-select-lg rounded-3" required onchange="checkStamp(this.value)">
                            <?php foreach ($rates as $key => $data): ?>
                                <option value="<?php echo $key; ?>" <?php echo $parcel['tariff'] === $key ? 'selected' : ''; ?>><?php echo e($data['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Вес (кг)</label>
                        <input type="number" name="weight" id="weightInput" class="form-control form-control-lg rounded-3" step="0.001" required value="<?php echo e($parcel['weight']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">ID Получателя</label>
                        <input type="number" name="recipient_id" class="form-control form-control-lg rounded-3" value="<?php echo e($parcel['recipient_id']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">ФИО Получателя (External)</label>
                        <input type="text" name="recipient_name_ext" class="form-control form-control-lg rounded-3" value="<?php echo e($parcel['recipient_name_ext']); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Откуда (ПВЗ / Адрес)</label>
                        <div class="input-group">
                            <select name="sender_pvz" class="form-select border-warning" style="max-width: 120px;">
                                <option value="">Адрес</option>
                                <option value="Минск_ЕН_Main" <?php echo ($parcel['sender_pvz'] ?? '') === 'Минск_ЕН_Main' ? 'selected' : ''; ?>>Минск_ЕН</option>
                            </select>
                            <textarea name="sender_address" class="form-control rounded-end-3" rows="1"><?php echo e($parcel['sender_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Куда (ПВЗ / Адрес)</label>
                        <div class="input-group">
                            <select name="pickup_point" class="form-select border-warning" style="max-width: 120px;">
                                <option value="">Адрес</option>
                                <option value="Минск_ЕН_Main" <?php echo ($parcel['pickup_point'] ?? '') === 'Минск_ЕН_Main' ? 'selected' : ''; ?>>Минск_ЕН</option>
                            </select>
                            <textarea name="address" class="form-control rounded-end-3" rows="1" required><?php echo e($parcel['address']); ?></textarea>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold text-muted small text-uppercase">Опись вложения (2%)</label>
                        <textarea name="inventory" class="form-control rounded-3" rows="2"><?php echo e($parcel['inventory'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Наложенный платеж (BYN)</label>
                        <input type="number" name="cod" id="codInput" class="form-control form-control-lg rounded-3" step="0.01" value="<?php echo e($parcel['cod']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Объявл. ценность (BYN)</label>
                        <input type="number" name="declared_value" id="dvInput" class="form-control form-control-lg rounded-3" step="0.01" value="<?php echo e($parcel['declared_value']); ?>">
                    </div>

                    <?php if($user['role'] === 'worker'): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-primary small text-uppercase">Партнёр по доставке</label>
                        <select name="delivery_partner" class="form-select form-select-lg rounded-3 border-primary shadow-none">
                            <option value="">-- Собственная доставка EHPST --</option>
                            <option value="Белпочта" <?php echo ($parcel['delivery_partner']??'')==='Белпочта'?'selected':'';?>>Белпочта</option>
                            <option value="Почта России" <?php echo ($parcel['delivery_partner']??'')==='Почта России'?'selected':'';?>>Почта России</option>
                            <option value="OZON" <?php echo ($parcel['delivery_partner']??'')==='OZON'?'selected':'';?>>OZON</option>
                            <option value="СДЭК" <?php echo ($parcel['delivery_partner']??'')==='СДЭК'?'selected':'';?>>СДЭК</option>
                            <option value="Wildberries" <?php echo ($parcel['delivery_partner']??'')==='Wildberries'?'selected':'';?>>Wildberries</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <script>
                    function checkStamp(val) {
                        const isStamp = (val === 'N' || val === 'P');
                        document.getElementById('weightInput').disabled = isStamp;
                        document.getElementById('codInput').disabled = isStamp;
                        document.getElementById('dvInput').disabled = isStamp;
                        document.getElementsByName('inventory')[0].disabled = isStamp;
                        if (isStamp) {
                            document.getElementById('weightInput').value = "1.000";
                            document.getElementById('codInput').value = "0.00";
                            document.getElementById('dvInput').value = "0.00";
                            document.getElementsByName('inventory')[0].value = "";
                        }
                    }
                    document.addEventListener('DOMContentLoaded', () => checkStamp(document.getElementById('tariffSelect').value));
                    </script>

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
