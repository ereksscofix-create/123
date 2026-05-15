<?php
// parcel_history.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID посылки не указан");

try {
    $stmt = $pdo->prepare("SELECT p.*, s.name AS sender_name, r.name AS recipient_name
                           FROM parcels p
                           JOIN users s ON p.sender_id = s.id
                           JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");

    $stmt = $pdo->prepare("SELECT * FROM parcel_status WHERE parcel_id = :id ORDER BY created_at DESC");
    $stmt->execute(['id' => $id]);
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

$page_title = "История посылки " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="mb-4">
    <a href="dashboard.php" class="btn btn-link text-decoration-none ps-0"><i class="bi bi-arrow-left"></i> Назад на дашборд</a>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Информация</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small d-block">Трек-код</label>
                    <span class="fw-bold fs-5 text-primary"><?php echo e($parcel['track_code']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">Отправитель</label>
                    <span><?php echo e($parcel['sender_name']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">Получатель</label>
                    <span><?php echo e($parcel['recipient_name']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block">Адрес доставки</label>
                    <span class="small"><?php echo nl2br(e($parcel['address'])); ?></span>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="text-muted small d-block">Вес</label>
                        <span class="fw-bold"><?php echo e($parcel['weight']); ?> кг</span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small d-block">Стоимость</label>
                        <span class="fw-bold"><?php echo number_format($parcel['cost'], 2); ?> BYN</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">История статусов</h5>
            </div>
            <div class="card-body">
                <?php if ($history): ?>
                    <div class="position-relative ps-4 border-start border-2 border-primary border-opacity-10" style="margin-left: 10px;">
                        <?php foreach ($history as $idx => $status): ?>
                            <div class="mb-4 position-relative">
                                <div class="position-absolute translate-middle-x" style="left: -24px; top: 0;">
                                    <div class="rounded-circle <?php echo $idx === 0 ? 'bg-primary' : 'bg-secondary bg-opacity-25'; ?>" style="width: 14px; height: 14px;"></div>
                                </div>
                                <div class="ms-2">
                                    <div class="fw-bold <?php echo $idx === 0 ? 'text-primary' : ''; ?>"><?php echo e($status['status_text']); ?></div>
                                    <div class="small text-muted"><?php echo date('d.m.Y H:i', strtotime($status['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mb-0 position-relative">
                                <div class="position-absolute translate-middle-x" style="left: -24px; top: 0;">
                                    <div class="rounded-circle bg-secondary bg-opacity-25" style="width: 14px; height: 14px;"></div>
                                </div>
                                <div class="ms-2">
                                    <div class="text-muted">Посылка оформлена в системе</div>
                                    <div class="small text-muted"><?php echo date('d.m.Y H:i', strtotime($parcel['created_at'])); ?></div>
                                </div>
                            </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">История статусов пуста. Посылка только что оформлена.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
