<?php
// stamp_verify.php — Проверка подлинности почтовой марки
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$error = '';
$parcel = null;
$verified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stamp_no_input = trim($_POST['stamp_no'] ?? '');
    $secret_code = trim($_POST['secret_code'] ?? '');

    // Извлекаем ID из номера марки (убираем "№" и ведущие нули)
    $stamp_id = (int)preg_replace('/[^0-9]/', '', $stamp_no_input);

    if ($stamp_id > 0 && $secret_code !== '') {
        try {
            // Проверяем соответствие ID и секретного кода в таблице parcel_codes
            $stmt = $pdo->prepare("
                SELECT p.*, s.name as s_name, r.name as r_name,
                (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) as last_status
                FROM parcels p
                JOIN parcel_codes pc ON p.id = pc.parcel_id
                LEFT JOIN users s ON p.sender_id = s.id
                LEFT JOIN users r ON p.recipient_id = r.id
                WHERE p.id = :pid AND pc.secret_code = :secret
                LIMIT 1
            ");
            $stmt->execute(['pid' => $stamp_id, 'secret' => $secret_code]);
            $parcel = $stmt->fetch();

            if ($parcel) {
                $verified = true;
            } else {
                $error = "Марка не найдена или секретный код не верен.";
            }
        } catch (PDOException $e) {
            $error = "Ошибка базы данных: " . $e->getMessage();
        }
    } else {
        $error = "Пожалуйста, введите номер марки и секретный код.";
    }
}

$page_title = "Проверка марки — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-dark text-white p-4 text-center">
                <i class="bi bi-patch-check-fill text-warning fs-1 mb-2"></i>
                <h4 class="mb-0 fw-bold">Проверка марки</h4>
                <p class="mb-0 small opacity-75">Проверка подлинности почтового отправления</p>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-octagon-fill me-2 fs-4"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($verified && $parcel): ?>
                    <div class="text-center mb-5 animate-fade-in">
                        <div class="display-1 text-success mb-2"><i class="bi bi-shield-check"></i></div>
                        <h3 class="fw-bold text-success">МАРКА ПОДЛИННА</h3>
                        <p class="text-muted">Данное отправление зарегистрировано в системе EHPST</p>
                    </div>

                    <div class="bg-light rounded-4 p-4 border border-success border-opacity-25 mb-4">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="x-small text-muted text-uppercase fw-bold">Трек-код</label>
                                <div class="fw-bold text-primary"><?php echo e($parcel['track_code']); ?></div>
                            </div>
                            <div class="col-6">
                                <label class="x-small text-muted text-uppercase fw-bold">Тариф</label>
                                <div class="fw-bold"><?php echo e($parcel['tariff']); ?></div>
                            </div>
                            <div class="col-12 border-top pt-2">
                                <label class="x-small text-muted text-uppercase fw-bold">Отправитель</label>
                                <div class="small"><?php echo e($parcel['s_name'] ?: 'ID '.$parcel['sender_id']); ?></div>
                            </div>
                            <div class="col-12 border-top pt-2">
                                <label class="x-small text-muted text-uppercase fw-bold">Получатель</label>
                                <div class="small"><?php echo e($parcel['r_name'] ?: 'ID '.$parcel['recipient_id']); ?></div>
                            </div>
                            <div class="col-12 border-top pt-2">
                                <label class="x-small text-muted text-uppercase fw-bold">Текущий статус</label>
                                <div class="badge bg-primary bg-opacity-10 text-primary p-2 mt-1 w-100 text-wrap text-start">
                                    <?php echo e($parcel['last_status'] ?: 'Оформлена'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="stamp_verify.php" class="btn btn-outline-secondary w-100 rounded-pill py-2 fw-bold">Проверить еще одну</a>
                <?php else: ?>
                    <form method="post" class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted small text-uppercase">Номер марки</label>
                            <input type="text" name="stamp_no" class="form-control form-control-lg border-2 shadow-none rounded-3" placeholder="Например: 00000001" required value="<?php echo e($stamp_no_input ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-muted small text-uppercase">Секретный код</label>
                            <input type="text" name="secret_code" class="form-control form-control-lg border-2 shadow-none rounded-3" placeholder="6-значный код" required>
                        </div>
                        <div class="col-12 mt-4 pt-2">
                            <button type="submit" class="btn btn-dark btn-lg w-100 rounded-pill py-3 fw-bold shadow-sm text-uppercase">
                                <i class="bi bi-search me-2"></i>Проверить марку
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-5">
                    <a href="dashboard.php" class="btn btn-link text-muted text-decoration-none small fw-bold">
                        <i class="bi bi-arrow-left me-1"></i>Вернуться в дашборд
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
