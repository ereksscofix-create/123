<?php
// my_qr_codes.php — Страница со всеми QR-кодами пользователя
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
$user_id = (int)$user['id'];
$page_title = "Мои QR-коды для получения — EHPST";

$ready_parcels = [];
try {
    // Получаем посылки, которые ожидают получения (для получателя или отправителя в случае возврата)
    $stmt = $pdo->prepare("
        SELECT p.*,
            (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status,
            COALESCE(s.name, s.login) as sender_name,
            COALESCE(r.name, r.login) as recipient_name
        FROM parcels p
        LEFT JOIN users s ON p.sender_id = s.id
        LEFT JOIN users r ON p.recipient_id = r.id
        WHERE ((p.recipient_id = :uid AND p.is_return = 0) OR (p.sender_id = :uid AND p.is_return = 1))
        AND p.is_deleted_by_recipient = 0
    ");
    $stmt->execute(['uid' => $user_id]);
    $all = $stmt->fetchAll();

    foreach ($all as $p) {
        $st = $p['last_status'] ?? '';
        if (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false) {

            // Получаем или генерируем код на СЕГОДНЯ
            $stmt_c = $pdo->prepare("SELECT code FROM parcel_codes WHERE parcel_id = :pid AND code_date = DATE(NOW()) LIMIT 1");
            $stmt_c->execute(['pid' => $p['id']]);
            $code = $stmt_c->fetchColumn();

            if (!$code) {
                $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $secret = substr(md5(time() . $p['id']), 0, 6);
                $stmt_i = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :c, :s, DATE(NOW()))");
                $stmt_i->execute(['pid' => $p['id'], 'c' => $code, 's' => $secret]);
            }

            $p['today_code'] = $code;
            $ready_parcels[] = $p;
        }
    }
} catch (PDOException $e) { $error = $e->getMessage(); }

include __DIR__ . '/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Мои QR-коды</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left me-1"></i>Назад</a>
    </div>

    <?php if (empty($ready_parcels)): ?>
        <div class="card p-5 text-center border-0 shadow-sm rounded-4">
            <i class="bi bi-qr-code fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">У вас пока нет посылок, готовых к выдаче.</h5>
            <p class="small text-muted">Как только посылка прибудет в ПВЗ, здесь появится QR-код.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($ready_parcels as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-lg border-0 rounded-4 overflow-hidden h-100">
                        <div class="card-header bg-success text-white py-3 text-center border-0">
                            <span class="fw-bold small text-uppercase" style="letter-spacing:1px;">Готова к получению</span>
                        </div>
                        <div class="card-body p-4 text-center">
                            <div class="qr-container mb-3 d-flex justify-content-center p-2 bg-white rounded-3 shadow-sm border" id="qr-<?php echo $p['id']; ?>"></div>

                            <h4 class="fw-bold text-primary mb-1"><?php echo e($p['track_code']); ?></h4>
                            <div class="small text-muted mb-3">Код получения: <span class="fw-bold text-dark fs-5" style="letter-spacing:3px;"><?php echo $p['today_code']; ?></span></div>

                            <div class="p-3 bg-light rounded-4 text-start mb-3">
                                <div class="x-small text-muted fw-bold text-uppercase mb-1">Откуда:</div>
                                <div class="small mb-2 fw-bold"><?php echo e($p['sender_name']); ?></div>
                                <div class="x-small text-muted fw-bold text-uppercase mb-1">Адрес выдачи:</div>
                                <div class="small fw-bold"><?php echo e($p['address']); ?></div>
                            </div>

                            <a href="parcel_pickup_qr.php?id=<?php echo $p['id']; ?>" class="btn btn-primary w-100 rounded-pill fw-bold mb-2">На весь экран / Печать</a>
                            <div class="x-small text-muted"><i class="bi bi-info-circle me-1"></i>Код обновляется ежедневно</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($ready_parcels as $p): ?>
    new QRCode(document.getElementById("qr-<?php echo $p['id']; ?>"), {
        text: "<?php echo $p['track_code'] . '|' . $p['today_code']; ?>",
        width: 180,
        height: 180,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    <?php endforeach; ?>
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
