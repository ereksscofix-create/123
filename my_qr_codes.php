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
        WHERE ((p.recipient_id = :uid AND p.is_return = 0 AND (p.is_deleted_by_recipient = 0 OR p.is_deleted_by_recipient IS NULL))
           OR (p.sender_id = :uid AND p.is_return = 1 AND (p.is_deleted_by_sender = 0 OR p.is_deleted_by_sender IS NULL)))
    ");
    $stmt->execute(['uid' => $user_id]);
    $all = $stmt->fetchAll();

    foreach ($all as $p) {
        $st = $p['last_status'] ?? '';
        // Генерируем QR для всех, кто в статусе ожидания или прибыл
        if (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false) {

            // Получаем или генерируем код на СЕГОДНЯ
            $stmt_c = $pdo->prepare("SELECT code, secret_code FROM parcel_codes WHERE parcel_id = :pid AND code_date = DATE(NOW()) LIMIT 1");
            $stmt_c->execute(['pid' => $p['id']]);
            $c_data = $stmt_c->fetch();

            if (!$c_data) {
                $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $secret = substr(md5(time() . $p['id']), 0, 6);
                $stmt_i = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :c, :s, DATE(NOW()))");
                $stmt_i->execute(['pid' => $p['id'], 'c' => $code, 's' => $secret]);
                $p['today_code'] = $code;
                $p['secret_code'] = $secret;
            } else {
                $p['today_code'] = $c_data['code'];
                $p['secret_code'] = $c_data['secret_code'];
            }

            $ready_parcels[] = $p;
        }
    }

    // Генерация BATCH-кода (Мастер-QR)
    $batch_code = null;
    if (count($ready_parcels) > 0) {
        $stmt_b = $pdo->prepare("SELECT code FROM user_batch_codes WHERE user_id = :uid AND code_date = DATE(NOW()) LIMIT 1");
        $stmt_b->execute(['uid' => $user_id]);
        $batch_code = $stmt_b->fetchColumn();

        if (!$batch_code) {
            $batch_code = 'M-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            try {
                $stmt_bi = $pdo->prepare("INSERT INTO user_batch_codes (user_id, code, code_date) VALUES (:uid, :c, DATE(NOW()))");
                $stmt_bi->execute(['uid' => $user_id, 'c' => $batch_code]);
            } catch (Exception $e) {
                // Если кто-то уже создал, просто берем
                $stmt_b->execute(['uid' => $user_id]);
                $batch_code = $stmt_b->fetchColumn();
            }
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

        <?php if ($batch_code): ?>
        <div class="card mb-5 border-0 shadow-lg rounded-5 overflow-hidden animate-fade-in" style="background: linear-gradient(135deg, #4361ee, #4895ef);">
            <div class="card-body p-4 p-md-5 text-white">
                <div class="row align-items-center">
                    <div class="col-lg-4 text-center mb-4 mb-lg-0">
                        <div class="bg-white p-3 rounded-4 shadow-sm d-inline-block" id="qr-batch"></div>
                    </div>
                    <div class="col-lg-8">
                        <div class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold mb-2">MASTER QR</div>
                        <h2 class="fw-extrabold mb-3">Получить всё сразу!</h2>
                        <p class="opacity-75 mb-4">У вас <span class="fw-bold fs-5"><?php echo count($ready_parcels); ?></span> посылки на выдачу. Покажите этот код сотруднику, чтобы получить все отправления одним сканированием.</p>
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-25 text-center flex-grow-1">
                                <div class="x-small text-uppercase fw-bold opacity-75">Код подтверждения:</div>
                                <div class="fs-2 fw-extrabold" style="letter-spacing: 5px;"><?php echo $batch_code; ?></div>
                            </div>
                            <button class="btn btn-white text-primary rounded-pill px-4 fw-bold py-3" onclick="window.print()"><i class="bi bi-printer"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="alert alert-info border-0 rounded-4 p-3 mb-5 d-flex align-items-center">
            <i class="bi bi-info-circle-fill fs-4 me-3"></i>
            <div>
                <div class="fw-bold">Как это работает?</div>
                <div class="small opacity-75">Покажите Мастер QR-код сотруднику ПВЗ. Он увидит список всех ваших посылок и сможет выдать их одним нажатием. Это экономит время!</div>
            </div>
        </div>
        <hr class="mb-5">
        <h4 class="fw-bold mb-4">Посылки по отдельности:</h4>
        <?php endif; ?>

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
    <?php if($batch_code): ?>
    new QRCode(document.getElementById("qr-batch"), {
        text: "BATCH|<?php echo $user_id; ?>|<?php echo $batch_code; ?>",
        width: 220, height: 220, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H
    });
    <?php endif; ?>

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
