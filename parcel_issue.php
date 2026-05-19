<?php
// parcel_issue.php — страница выдачи посылки работником
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

if (isset($_GET['debug'])) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$user = currentUser();
if (!$user || $user['role'] !== 'worker') {
    die("Доступ запрещен. Только для работников.");
}

$page_title = "Выдача посылки — EHPST";
$name = $user['name'] ?: $user['login'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $track = trim($_POST['track'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $is_batch = (strpos($code, 'M-') === 0);
    $batch_uid = (int)($_POST['batch_uid'] ?? 0);

    $secret = trim($_POST['secret'] ?? '');
    $passport = trim($_POST['passport'] ?? '');
    $loyalty_card_num = trim($_POST['loyalty_card'] ?? '');

    try {
        $parcels_to_issue = [];

        if ($is_batch && $batch_uid > 0) {
            // Массовая выдача по Мастер-QR
            $stmt = $pdo->prepare("SELECT code FROM user_batch_codes WHERE user_id = :uid AND code = :code AND code_date = DATE(NOW()) LIMIT 1");
            $stmt->execute(['uid' => $batch_uid, 'code' => $code]);
            if ($stmt->fetch()) {
                // Ищем все посылки этого пользователя, готовые к выдаче
                $stmt = $pdo->prepare("
                    SELECT p.*, (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) as last_status
                    FROM parcels p
                    WHERE ((p.recipient_id = :uid AND p.is_return = 0) OR (p.sender_id = :uid AND p.is_return = 1))
                ");
                $stmt->execute(['uid' => $batch_uid]);
                $all_u = $stmt->fetchAll();
                foreach($all_u as $pu) {
                    $st = $pu['last_status'] ?? '';
                    if (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false) {
                        $parcels_to_issue[] = $pu;
                    }
                }
                if (empty($parcels_to_issue)) $error = "Нет посылок, готовых к выдаче для этого пользователя.";
            } else {
                $error = "Неверный или истекший BATCH-код.";
            }
        } else {
            // Одиночная выдача
            $stmt = $pdo->prepare("SELECT id, track_code, sender_id, recipient_id, is_paid, pay_on_delivery, cod, is_cod_paid, shelf, pickup_point, is_return, cod_return_required, tariff, weight FROM parcels WHERE track_code = :track LIMIT 1");
            $stmt->execute(['track' => $track]);
            $p = $stmt->fetch();
            if ($p) $parcels_to_issue[] = $p;
            else $error = "Посылка с таким трек-кодом не найдена.";
        }

        foreach ($parcels_to_issue as $parcel) {
            $is_already_issued = false;
            // Проверка: Не выдана ли уже? (Если это ВОЗВРАТ, то игнорируем прошлые выдачи)
            if ((int)($parcel['is_return'] ?? 0) === 0) {
                $stmt_check = $pdo->prepare("SELECT id FROM parcel_status WHERE parcel_id = :pid AND (status_text LIKE '%выдана%' OR status_text LIKE '%доставлено%') LIMIT 1");
                $stmt_check->execute(['pid' => $parcel['id']]);
                if ($stmt_check->fetch()) {
                    $error .= "Посылка {$parcel['track_code']} уже была выдана ранее.<br>";
                    $is_already_issued = true;
                }
            } else {
                // Если это возврат, проверяем, не был ли УЖЕ выдан сам возврат
                $stmt_check = $pdo->prepare("SELECT id FROM parcel_status WHERE parcel_id = :pid AND status_text LIKE 'Возврат выдан%' LIMIT 1");
                $stmt_check->execute(['pid' => $parcel['id']]);
                if ($stmt_check->fetch()) {
                    $error .= "Возврат {$parcel['track_code']} уже был выдан ранее.<br>";
                    $is_already_issued = true;
                }
            }

            if ($is_already_issued) continue;

            $success_info = "";
            if (!empty($parcel['pickup_point']) && !empty($parcel['shelf'])) {
                $success_info = "<div class='p-2 bg-primary text-white rounded-3 mb-2 small'>📦 ПОЛКА: <span class='fw-bold ms-1'>{$parcel['shelf']}</span> [{$parcel['track_code']}]</div>";
            }
            $parcel_id = (int)$parcel['id'];
            $can_issue = false;
            $method = '';

            if ($is_batch) {
                $can_issue = true;
                $method = "по BATCH-коду ($code)";
            } elseif ($code !== '') {
                $stmt = $pdo->prepare("SELECT id FROM parcel_codes WHERE parcel_id = :pid AND code = :code AND code_date = DATE(NOW()) LIMIT 1");
                $stmt->execute(['pid' => $parcel_id, 'code' => $code]);
                if ($stmt->fetch()) {
                    $can_issue = true;
                    $method = "по коду ($code)";
                }
            }

            if (!$can_issue && $secret !== '') {
                $stmt = $pdo->prepare("SELECT id FROM parcel_codes WHERE parcel_id = :pid AND secret_code = :secret AND code_date = DATE(NOW()) LIMIT 1");
                $stmt->execute(['pid' => $parcel_id, 'secret' => $secret]);
                if ($stmt->fetch()) {
                    $can_issue = true;
                    $method = "по секретному коду";
                }
            }

            if (!$can_issue && $passport !== '') {
                $can_issue = true;
                $method = "по паспорту ($passport)";
            }

            if (!$can_issue && $loyalty_card_num !== '') {
                $stmt = $pdo->prepare("SELECT user_id FROM loyalty_cards WHERE card_number = :cn LIMIT 1");
                $stmt->execute(['cn' => $loyalty_card_num]);
                $card_uid = $stmt->fetchColumn();

                $target_uid = ((int)($parcel['is_return'] ?? 0) === 1) ? (int)$parcel['sender_id'] : (int)$parcel['recipient_id'];

                if ($card_uid && (int)$card_uid === $target_uid) {
                    $can_issue = true;
                    $method = "по карте лояльности ($loyalty_card_num)";
                }
            }

            if ($can_issue) {
                if ($parcel['cod_return_required']) {
                    $error = "<div class='p-3 bg-danger text-white rounded-3'>🚨 ВНИМАНИЕ: Необходимо вернуть наложенный платеж (" . number_format($parcel['cod'], 2) . " BYN), так как он уже был выплачен вам ранее.<br><a href='parcel_cod_repay.php?id={$parcel['id']}' class='btn btn-light btn-sm mt-2 fw-bold'>ВЕРНУТЬ ДЕНЬГИ В КАССУ</a></div>";
                    $can_issue = false;
                }

                if ($can_issue && !$parcel['is_paid']) {
                    $error = "<div class='p-3 bg-danger text-white rounded-3'>🚨 ВНИМАНИЕ: Посылка НЕ ОПЛАЧЕНА! Выдача категорически запрещена.<br><a href='parcel_pay.php?id={$parcel['id']}' class='btn btn-light btn-sm mt-2 fw-bold'>ОПЛАТИТЬ УСЛУГИ СВЯЗИ</a></div>";
                    $can_issue = false;
                }

                if ($can_issue && $parcel['cod'] > 0 && !$parcel['is_cod_paid']) {
                    $error = "<div class='p-3 bg-danger text-white rounded-3'>🚨 ВНИМАНИЕ: Ожидается оплата НАЛОЖЕННОГО ПЛАТЕЖА!<br><a href='parcel_pay_cod.php?id={$parcel['id']}' class='btn btn-light btn-sm mt-2 fw-bold'>ОПЛАТИТЬ НАЛ.ПЛАТЕЖ (" . number_format($parcel['cod'], 2) . " BYN)</a></div>";
                    $can_issue = false;
                }
            }

            if ($can_issue) {
                $worker_name = $user['name'] ?: $user['login'];
                $is_ret = (int)($parcel['is_return'] ?? 0) === 1;
                $prefix = $is_ret ? "Возврат выдан" : "Выдана";
                $status_text = "$prefix $method [" . date('d.m.Y H:i') . "] (Оператор: $worker_name)";
                $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
                $stmt->execute(['pid' => $parcel_id, 'txt' => $status_text]);

                $target_uid = ((int)($parcel['is_return'] ?? 0) === 1) ? $parcel['sender_id'] : $parcel['recipient_id'];
                notifyUser($target_uid, "Ваша посылка {$parcel['track_code']} успешно выдана.");

                $btn_receipt = "";
                if ($is_batch) {
                    $btn_receipt = "<a href='parcel_receipt.php?id={$parcel['id']}' class='btn btn-light btn-sm ms-2 x-small border'>Чек</a>";
                }

                $success .= $success_info . "Посылка {$parcel['track_code']} выдана! $btn_receipt<br>";
            } else {
                $error .= "Ошибка выдачи {$parcel['track_code']}: Неверный код или неоплачено.<br>";
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
        <div class="card shadow-sm rounded-4 border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-box-arrow-right text-success me-2"></i>Выдача посылки</h5>
                    <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="startScanner()"><i class="bi bi-qr-code-scan me-1"></i>Сканировать</button>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success rounded-4 border-0 shadow-sm p-4 mb-4"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-4 border-0 shadow-sm p-4 mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

                <div id="scanner-container" style="display:none;" class="mb-4 text-center">
                    <div id="reader" style="width:100%; max-width:400px; margin:0 auto; border-radius: 15px; overflow: hidden;"></div>
                    <button class="btn btn-light mt-2 rounded-pill" onclick="stopScanner()">Отмена</button>
                </div>

                <form method="post" id="issueForm">
                    <input type="hidden" name="batch_uid" id="batchUidInput" value="0">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Трек-код или BATCH-ID</label>
                        <input type="text" name="track" id="trackInput" class="form-control form-control-lg rounded-3" required placeholder="EP123456789BY" autofocus>
                    </div>

                    <hr class="my-4">
                    <p class="text-muted small">Подтвердите личность одним из способов:</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Код из СМС / приложения</label>
                        <input type="text" name="code" id="codeInput" class="form-control form-control-lg rounded-3" placeholder="4-значный код">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Секретный код с ярлыка (если есть)</label>
                        <input type="text" name="secret" class="form-control" placeholder="Секретный код">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Номер карты лояльности</label>
                        <input type="text" name="loyalty_card" class="form-control" placeholder="5000XXXXXXXXXXXX">
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

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let html5QrCode = null;

function startScanner() {
    document.getElementById('scanner-container').style.display = 'block';
    html5QrCode = new Html5Qrcode("reader");
    const config = { fps: 10, qrbox: { width: 250, height: 250 } };

    html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
}

function onScanSuccess(decodedText, decodedResult) {
    stopScanner();
    // Формат QR: TRACK|CODE или BATCH|USER_ID|CODE
    if (decodedText.includes('|')) {
        const parts = decodedText.split('|');
        if (parts[0] === 'BATCH') {
            document.getElementById('trackInput').value = 'BATCH_MODE';
            document.getElementById('batchUidInput').value = parts[1];
            document.getElementById('codeInput').value = parts[2];
        } else {
            document.getElementById('trackInput').value = parts[0];
            document.getElementById('codeInput').value = parts[1];
        }
        document.getElementById('issueForm').submit();
    } else {
        document.getElementById('trackInput').value = decodedText;
    }
}

function stopScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            document.getElementById('scanner-container').style.display = 'none';
        });
    }
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
