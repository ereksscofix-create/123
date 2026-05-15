<?php
// parcel_issue.php — страница выдачи посылки работником
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if ($user['role'] !== 'worker') {
    die("Доступ запрещен. Только для работников.");
}

$page_title = "Выдача посылки — EHPST";
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $track = trim($_POST['track'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $secret = trim($_POST['secret'] ?? '');
    $passport = trim($_POST['passport'] ?? '');

    try {
        // Ищем посылку
        $stmt = $pdo->prepare("SELECT id, recipient_id FROM parcels WHERE track_code = :track LIMIT 1");
        $stmt->execute(['track' => $track]);
        $parcel = $stmt->fetch();

        if (!$parcel) {
            $error = "Посылка с таким трек-кодом не найдена.";
        } else {
            $parcel_id = (int)$parcel['id'];
            $recipient_id = (int)$parcel['recipient_id'];
            $can_issue = false;
            $method = '';

            // 1. Проверка по коду получения
            if ($code !== '') {
                $stmt = $pdo->prepare("SELECT id FROM parcel_codes WHERE parcel_id = :pid AND code = :code AND code_date = DATE(NOW()) LIMIT 1");
                $stmt->execute(['pid' => $parcel_id, 'code' => $code]);
                if ($stmt->fetch()) {
                    $can_issue = true;
                    $method = "по коду ($code)";
                }
            }

            // 2. Проверка по секретному коду
            if (!$can_issue && $secret !== '') {
                $stmt = $pdo->prepare("SELECT id FROM parcel_codes WHERE parcel_id = :pid AND secret_code = :secret AND code_date = DATE(NOW()) LIMIT 1");
                $stmt->execute(['pid' => $parcel_id, 'secret' => $secret]);
                if ($stmt->fetch()) {
                    $can_issue = true;
                    $method = "по секретному коду";
                }
            }

            // 3. Выдача по паспорту
            if (!$can_issue && $passport !== '') {
                $can_issue = true;
                $method = "по паспорту ($passport)";
            }

            if ($can_issue) {
                // Обновляем статус
                $status_text = "Выдана " . $method;
                $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
                $stmt->execute(['pid' => $parcel_id, 'txt' => $status_text]);

                // Уведомляем получателя
                notifyUser($recipient_id, "Ваша посылка $track успешно выдана.");

                $success = "Посылка успешно выдана! Метод: $method";
            } else {
                $error = "Не удалось подтвердить выдачу. Проверьте код или введите данные паспорта.";
            }
        }
    } catch (PDOException $e) {
        $error = "Ошибка БД: " . $e->getMessage();
    }
}

include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-box-arrow-right text-success me-2"></i>Выдача посылки</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Трек-код посылки</label>
                        <input type="text" name="track" class="form-control form-control-lg" required placeholder="Напр. EP123456789BY" autofocus>
                    </div>

                    <hr class="my-4">
                    <p class="text-muted small">Подтвердите личность одним из способов:</p>

                    <div class="mb-3">
                        <label class="form-label">Код из СМС / приложения</label>
                        <input type="text" name="code" class="form-control" placeholder="4-значный код">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Секретный код с ярлыка (если есть)</label>
                        <input type="text" name="secret" class="form-control" placeholder="Секретный код">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Серия и номер паспорта</label>
                        <input type="text" name="passport" class="form-control" placeholder="Напр. AB 1234567">
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-success w-100 btn-lg">Выдать посылку</button>
                        <a href="dashboard.php" class="btn btn-link w-100 text-muted mt-2">Вернуться на дашборд</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
