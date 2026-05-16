<?php
// parcel_status.php — изменение статуса посылки работником
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') {
    die("Доступ запрещен.");
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID не указан");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");

    // ВАЖНО: Работник может менять статус любой посылки, но обычный пользователь — только своей (если бы у него была кнопка)
    // Но в нашей системе менять статус может только worker, так что проверка роли выше достаточна.
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status_text'] ?? '');
    $custom_status = trim($_POST['custom_status'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $final_status = ($status === 'custom') ? $custom_status : $status;

    if ($final_status !== '') {
        try {
            // Добавляем информацию о том, кто изменил статус, прямо в текст статуса (для истории)
            $worker_name = $user['name'] ?: $user['login'];
            $full_status_text = $final_status;
            if ($location !== '') {
                $full_status_text .= " [" . $location . "]";
            }
            $full_status_text .= " (Оператор: " . $worker_name . ")";

            // Резольвер для created_at: Всегда добавляем время в текст статуса для надежности,
            // но INSERT делаем без колонки created_at, чтобы избежать ошибок схемы.
            $full_status_text .= " [" . date('d.m.Y H:i') . "]";

            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $id, 'txt' => $full_status_text]);

            // Формируем сообщение для уведомления
            $date_str = date('d.m.Y H:i');
            $notif_msg = "Статус посылки {$parcel['track_code']} изменен на: \"$final_status\". Дата: $date_str. Изменил: $worker_name.";

            if (mb_stripos($final_status, 'ожидает') !== false && mb_stripos($final_status, 'получения') !== false) {
                $code = rand(1000, 9999);
                $secret = rand(100000, 999999);
                $stmt = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :code, :secret, DATE(NOW()))");
                $stmt->execute(['pid' => $id, 'code' => $code, 'secret' => $secret]);

                $notif_msg .= " Ваш код получения: $code";
                notifyUser($parcel['recipient_id'], $notif_msg, 'info', true);
            } else {
                notifyUser($parcel['recipient_id'], $notif_msg);
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

<div class="row justify-content-center py-4">
    <div class="col-md-6">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white py-3 border-0 rounded-top-4">
                <h5 class="mb-0 fw-bold">Обновление статуса: <?php echo e($parcel['track_code']); ?></h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Выберите новый статус</label>
                        <select name="status_text" class="form-select form-select-lg mb-2 shadow-none border-2" onchange="toggleCustomStatus(this.value)">
                            <option value="В пути">В пути</option>
                            <option value="Прибыло в сортировочный центр">Прибыло в сортировочный центр</option>
                            <option value="Ожидает получения">Ожидает получения (генерирует код)</option>
                            <option value="Доставлено">Доставлено</option>
                            <option value="custom">-- Свой вариант --</option>
                        </select>
                        <div id="custom_status_div" style="display:none;" class="mb-2">
                            <input type="text" name="custom_status" class="form-control form-control-lg border-2 shadow-none" placeholder="Введите статус вручную...">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Местоположение / Город</label>
                        <input type="text" name="location" class="form-control form-control-lg border-2 shadow-none" placeholder="Например: Минск СЦ-1">
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill shadow-sm fw-bold">ПОДТВЕРДИТЬ ИЗМЕНЕНИЕ</button>
                        <a href="dashboard.php" class="btn btn-link w-100 text-muted mt-2 text-decoration-none small fw-bold">ОТМЕНА</a>
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
