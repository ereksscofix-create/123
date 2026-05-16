<?php
// parcel_history.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID посылки не указан");

try {
    $stmt = $pdo->prepare("SELECT p.*,
                           COALESCE(s.name, s.login, CONCAT('ID ', p.sender_id)) AS sender_name,
                           COALESCE(r.name, r.login, CONCAT('ID ', p.recipient_id)) AS recipient_name
                           FROM parcels p
                           LEFT JOIN users s ON p.sender_id = s.id
                           LEFT JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :id");
    $stmt->execute(['id' => $id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");

    // Загружаем историю статусов
    $stmt = $pdo->prepare("SELECT * FROM parcel_status WHERE parcel_id = :id ORDER BY id DESC");
    $stmt->execute(['id' => $id]);
    $history = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Ошибка БД: " . $e->getMessage());
}

$page_title = "История " . $parcel['track_code'];
include __DIR__ . '/header.php';
?>

<div class="mb-4">
    <a href="dashboard.php" class="btn btn-outline-secondary border-0 rounded-pill px-3 fw-bold"><i class="bi bi-arrow-left me-2"></i>На главную</a>
</div>

<div class="row g-4">
    <!-- Сводка по посылке -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100">
            <div class="card-header bg-primary text-white p-4 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 p-2 rounded-circle me-3"><i class="bi bi-box-seam fs-3"></i></div>
                    <h5 class="mb-0 fw-bold"><?php echo e($parcel['track_code']); ?></h5>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-1">Маршрут</label>
                    <div class="d-flex align-items-center">
                        <span class="fw-bold text-dark"><?php echo e($parcel['sender_name']); ?></span>
                        <i class="bi bi-arrow-right mx-2 text-primary"></i>
                        <span class="fw-bold text-dark"><?php echo e($parcel['recipient_name']); ?></span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-1">Адрес доставки</label>
                    <div class="bg-light p-3 rounded-3 small border-start border-primary border-3">
                        <?php echo nl2br(e($parcel['address'])); ?>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <label class="text-muted x-small fw-bold text-uppercase d-block mb-1">Вес</label>
                            <span class="h6 mb-0 fw-bold text-dark"><?php echo number_format($parcel['weight'], 3); ?> кг</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <label class="text-muted x-small fw-bold text-uppercase d-block mb-1">Сумма</label>
                            <span class="h6 mb-0 fw-bold text-success"><?php echo number_format($parcel['cost'], 2); ?> BYN</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Таймлайн статусов -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">История перемещений</h5>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($history): ?>
                    <div class="timeline-v2">
                        <?php foreach ($history as $idx => $st): ?>
                            <div class="timeline-item-v2 <?php echo $idx === 0 ? 'active' : ''; ?>">
                                <div class="timeline-marker-v2 <?php echo $idx === 0 ? 'bg-primary' : 'bg-light border border-primary border-opacity-50'; ?>"></div>
                                <div class="timeline-content-v2">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold text-dark mb-1 pe-3"><?php echo e($st['status_text']); ?></h6>
                                        <div class="badge <?php echo $idx === 0 ? 'bg-primary' : 'bg-light text-muted border'; ?> rounded-pill py-2 px-3 fw-bold shadow-sm">
                                            <i class="bi bi-calendar3 me-2"></i>
                                            <?php
                                            // Отображаем дату из БД. Если поле пустое, показываем текущую дату (как заглушку)
                                            $st_date = !empty($st['created_at']) ? $st['created_at'] : date('Y-m-d H:i:s');
                                            echo date('d.m.Y · H:i', strtotime($st_date));
                                            ?>
                                        </div>
                                    </div>
                                    <?php if($idx === 0): ?>
                                        <div class="small text-primary fw-bold mb-0 mt-1"><i class="bi bi-geo-alt-fill me-1"></i> Текущее местоположение</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <!-- Начальная точка -->
                        <div class="timeline-item-v2 last">
                            <div class="timeline-marker-v2 bg-light border border-secondary border-opacity-25"></div>
                            <div class="timeline-content-v2">
                                <h6 class="fw-bold text-muted mb-0">Принято к оформлению</h6>
                                <div class="x-small text-muted mt-1">Начальная обработка данных</div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-light p-4 rounded-circle d-inline-block mb-3 shadow-sm"><i class="bi bi-lightning-charge fs-1 text-primary"></i></div>
                        <p class="text-muted fw-bold">Посылка успешно зарегистрирована в системе и ожидает первой обработки.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Кастомный стиль таймлайна */
.timeline-v2 { position: relative; padding-left: 30px; }
.timeline-v2::before { content: ''; position: absolute; left: 6px; top: 10px; bottom: 10px; width: 3px; background: linear-gradient(to bottom, var(--primary), #eef2f7); border-radius: 10px; }
.timeline-item-v2 { position: relative; margin-bottom: 40px; }
.timeline-item-v2.active { transform: scale(1.02); transition: 0.3s; }
.timeline-item-v2.last { margin-bottom: 0; }
.timeline-marker-v2 { position: absolute; left: -30px; top: 5px; width: 16px; height: 14px; border-radius: 50%; z-index: 2; transition: 0.3s; }
.timeline-item-v2.active .timeline-marker-v2 { width: 14px; height: 14px; box-shadow: 0 0 0 6px rgba(67, 97, 238, 0.15); }
.timeline-content-v2 { background: #fff; padding-left: 5px; }

@media (max-width: 768px) {
    .timeline-v2 { padding-left: 20px; }
    .timeline-marker-v2 { left: -20px; }
    .timeline-v2::before { left: 4px; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
