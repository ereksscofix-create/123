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

    // Сортируем по id, так как created_at может отсутствовать
    $stmt = $pdo->prepare("SELECT * FROM parcel_status WHERE parcel_id = :id ORDER BY id DESC");
    $stmt->execute(['id' => $id]);
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

$page_title = "История посылки " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="mb-4">
    <a href="dashboard.php" class="btn btn-link text-decoration-none ps-0 text-primary fw-bold"><i class="bi bi-arrow-left"></i> Назад в дашборд</a>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">Информация</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small d-block fw-bold text-uppercase">Трек-код</label>
                    <span class="fw-bold fs-5 text-primary"><?php echo e($parcel['track_code']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block fw-bold text-uppercase">Отправитель</label>
                    <span class="fw-semibold text-dark"><?php echo e($parcel['sender_name']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block fw-bold text-uppercase">Получатель</label>
                    <span class="fw-semibold text-dark"><?php echo e($parcel['recipient_name']); ?></span>
                </div>
                <div class="mb-3">
                    <label class="text-muted small d-block fw-bold text-uppercase">Адрес</label>
                    <span class="small"><?php echo nl2br(e($parcel['address'])); ?></span>
                </div>
                <div class="row g-2 mt-2 pt-3 border-top">
                    <div class="col-6">
                        <label class="text-muted small d-block fw-bold text-uppercase">Вес</label>
                        <span class="fw-bold text-dark"><?php echo e($parcel['weight']); ?> кг</span>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small d-block fw-bold text-uppercase">Стоимость</label>
                        <span class="fw-bold text-success"><?php echo number_format($parcel['cost'], 2); ?> BYN</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">История перемещений</h5>
            </div>
            <div class="card-body">
                <?php if ($history): ?>
                    <div class="position-relative ps-4 border-start border-2 border-primary border-opacity-10 ms-2">
                        <?php foreach ($history as $idx => $status): ?>
                            <div class="mb-4 position-relative">
                                <div class="position-absolute translate-middle-x" style="left: -25px; top: 5px;">
                                    <div class="rounded-circle <?php echo $idx === 0 ? 'bg-primary shadow' : 'bg-secondary bg-opacity-25'; ?>" style="width: 14px; height: 14px;"></div>
                                </div>
                                <div class="ms-3">
                                    <div class="fw-bold <?php echo $idx === 0 ? 'text-primary' : 'text-dark'; ?> mb-1"><?php echo e($status['status_text']); ?></div>
                                    <div class="small text-muted">
                                        <?php if(!empty($status['created_at'])): ?>
                                            <i class="bi bi-clock me-1"></i><?php echo date('d.m.Y H:i', strtotime($status['created_at'])); ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted fw-normal">Дата не указана</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-info-circle fs-1 text-muted opacity-25 d-block mb-3"></i>
                        <p class="text-muted fw-bold">История статусов пока пуста</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
