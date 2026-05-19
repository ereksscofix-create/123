<?php
// parcel_pickup_qr.php — Dedicated page for pickup QR code
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

if (!$id) die("ID не указан.");

try {
    $stmt = $pdo->prepare("SELECT p.*,
        (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status,
        COALESCE(s.name, s.login) as sender_name,
        COALESCE(r.name, r.login) as recipient_name
        FROM parcels p
        LEFT JOIN users s ON p.sender_id = s.id
        LEFT JOIN users r ON p.recipient_id = r.id
        WHERE p.id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();

    if (!$parcel) die("Посылка не найдена.");

    // Access control: Worker or Recipient (or Sender if it is a return)
    $is_p_recip = ((int)$parcel['recipient_id'] === (int)$user['id'] && (int)($parcel['is_return']??0) === 0);
    $is_p_sender_ret = ((int)$parcel['sender_id'] === (int)$user['id'] && (int)($parcel['is_return']??0) === 1);

    if ($user['role'] !== 'worker' && !$is_p_recip && !$is_p_sender_ret) {
        die("У вас нет доступа к этой посылке.");
    }

    // Status check (Optional, but good for UX)
    // if (mb_stripos($parcel['last_status'] ?? '', 'ожидает') === false && mb_stripos($parcel['last_status'] ?? '', 'прибыло') === false) {
    //     die("QR-код доступен только для посылок в статусе ожидания выдачи.");
    // }

    // Get or generate pickup code
    $stmt = $pdo->prepare("SELECT code FROM parcel_codes WHERE parcel_id = :pid AND code_date = DATE(NOW()) LIMIT 1");
    $stmt->execute(['pid' => $id]);
    $code = $stmt->fetchColumn();

    if (!$code) {
        $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $secret = substr(md5(time() . $id), 0, 6);
        $stmt = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :c, :s, DATE(NOW()))");
        $stmt->execute(['pid' => $id, 'c' => $code, 's' => $secret]);
    }

    $qr_data = $parcel['track_code'] . "|" . $code;

} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$page_title = "Код получения — " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-6 text-center">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-success text-white p-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-qr-code me-2"></i>Код для получения</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="mb-4">
                    <div id="pickup-qr" class="d-flex justify-content-center mb-4 p-3 bg-white shadow-sm rounded-4" style="display:inline-block;"></div>
                    <h3 class="fw-bold text-primary mb-1"><?php echo e($parcel['track_code']); ?></h3>
                    <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold mb-3"><?php echo e($parcel['tariff']); ?> · <?php echo number_format($parcel['weight'], 3); ?> кг</div>
                </div>

                <div class="row g-3 mb-4 text-start">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <div class="x-small text-muted fw-bold text-uppercase mb-1">Отправитель</div>
                            <div class="small fw-bold text-dark"><?php echo e($parcel['sender_name']); ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <div class="x-small text-muted fw-bold text-uppercase mb-1">Получатель</div>
                            <div class="small fw-bold text-dark"><?php echo e($parcel['recipient_name']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-4 mb-4">
                    <div class="small text-uppercase fw-bold text-muted mb-2">Ваш цифровой код:</div>
                    <div class="display-5 fw-extrabold text-dark" style="letter-spacing: 5px;"><?php echo $code; ?></div>
                </div>

                <div class="alert alert-info border-0 rounded-4 small mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Код действителен в течение сегодняшнего дня. Если вы не заберете посылку сегодня, завтра будет сгенерирован новый код.
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary rounded-pill py-3 fw-bold" onclick="window.print()">
                        <i class="bi bi-printer me-2"></i>Распечатать или сохранить
                    </button>
                    <a href="dashboard.php" class="btn btn-light rounded-pill py-3">Вернуться в дашборд</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById("pickup-qr"), {
        text: "<?php echo $qr_data; ?>",
        width: 250,
        height: 250,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
</script>

<style>
@media print {
    body * { visibility: hidden; }
    .card, .card * { visibility: visible; }
    .card { position: absolute; left: 0; top: 0; width: 100%; border: none !important; box-shadow: none !important; }
    .btn, .alert { display: none !important; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
