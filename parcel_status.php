<?php
// parcel_status.php — изменение статуса посылки работником
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if ($user['role'] !== 'worker') {
    die("Доступ запрещен.");
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status_text'] ?? '');
    $custom_status = trim($_POST['custom_status'] ?? '');

    $final_status = ($status === 'custom') ? $custom_status : $status;

    if ($final_status !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $id, 'txt' => $final_status]);

            // Если статус "Ожидает получения", генерируем код
            if (mb_stripos($final_status, 'ожидает') !== false && mb_stripos($final_status, 'получения') !== false) {
                $code = rand(1000, 9999);
                $secret = rand(100000, 999999);
                $stmt = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :code, :secret, DATE(NOW()))");
                $stmt->execute(['pid' => $id, 'code' => $code, 'secret' => $secret]);

                notifyUser($parcel['recipient_id'], "Ваша посылка {$parcel['track_code']} ожидает получения! Ваш код: $code", 'info', true);
            } else {
                notifyUser($parcel['recipient_id'], "Статус вашей посылки {$parcel['track_code']} обновлен: $final_status");
            }

            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            $error = "Ошибка БД: " . $e->getMessage();
        }
    } else {
        $error = "Введите текст статуса.";
    }
}

$page_title = "Смена статуса " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Смена статуса для <?php echo e($parcel['track_code']); ?></h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Выберите статус или введите свой</label>
                        <select name="status_text" class="form-select mb-2" onchange="toggleCustomStatus(this.value)">
                            <option value="В пути">В пути</option>
                            <option value="Прибыло в сортировочный центр">Прибыло в сортировочный центр</option>
                            <option value="Ожидает получения">Ожидает получения (сгенерирует код)</option>
                            <option value="Доставлено">Доставлено</option>
                            <option value="custom">-- Свой вариант --</option>
                        </select>
                        <div id="custom_status_div" style="display:none;">
                            <input type="text" name="custom_status" class="form-control" placeholder="Введите статус...">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary w-100">Обновить статус</button>
                        <a href="dashboard.php" class="btn btn-link w-100 text-muted mt-2">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomStatus(val) {
    document.getElementById('custom_status_div').style.display = (val === 'custom') ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
