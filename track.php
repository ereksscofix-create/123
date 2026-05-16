<?php
// track.php — Публичное отслеживание посылок
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$q = trim((string)($_GET['q'] ?? ''));
$parcel = null;
$history = [];
$followed = false;
$user = currentUser();

if ($q !== '') {
    try {
        $stmt = $pdo->prepare("SELECT p.*, s.name as s_name, r.name as r_name
                               FROM parcels p
                               LEFT JOIN users s ON p.sender_id = s.id
                               LEFT JOIN users r ON p.recipient_id = r.id
                               WHERE p.track_code = :q LIMIT 1");
        $stmt->execute(['q' => $q]);
        $parcel = $stmt->fetch();

        if ($parcel) {
            $stmt = $pdo->prepare("SELECT * FROM parcel_status WHERE parcel_id = :id ORDER BY id DESC");
            $stmt->execute(['id' => $parcel['id']]);
            $history = $stmt->fetchAll();

            if ($user) {
                $stmt = $pdo->prepare("SELECT id FROM parcel_followers WHERE user_id = :uid AND parcel_id = :pid");
                $stmt->execute(['uid' => $user['id'], 'pid' => $parcel['id']]);
                $followed = (bool)$stmt->fetch();
            }
        }
    } catch (PDOException $e) { $error = $e->getMessage(); }
}

$page_title = "Отследить посылку — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
            <div class="card-body p-4 p-md-5">
                <h3 class="fw-bold mb-4 text-center">Где моя посылка? 🔍</h3>
                <form method="get" class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                    <input type="text" name="q" class="form-control border-0 px-4" placeholder="Введите трек-код (напр. ST...)" value="<?php echo e($q); ?>" required>
                    <button class="btn btn-primary px-4 border-0 rounded-0"><i class="bi bi-search"></i></button>
                </form>
            </div>
        </div>

        <?php if ($q !== '' && !$parcel): ?>
            <div class="alert alert-warning rounded-4 border-0 shadow-sm p-4 text-center">
                <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
                Посылка с кодом <strong><?php echo e($q); ?></strong> не найдена. Проверьте правильность ввода.
            </div>
        <?php endif; ?>

        <?php if ($parcel): ?>
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden animate-fade-in">
                <div class="card-header bg-primary text-white p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="x-small text-uppercase opacity-75 fw-bold">Трек-номер</div>
                        <h4 class="mb-0 fw-bold"><?php echo e($parcel['track_code']); ?></h4>
                    </div>
                    <?php if($user): ?>
                        <?php if(!$followed): ?>
                            <a href="parcel_follow.php?id=<?php echo $parcel['id']; ?>&q=<?php echo e($q); ?>" class="btn btn-white btn-sm text-primary rounded-pill fw-bold">
                                <i class="bi bi-plus-circle me-1"></i> В КАНЦЕЛЯРИЮ
                            </a>
                        <?php else: ?>
                            <span class="badge bg-white text-primary rounded-pill py-2 px-3 fw-bold shadow-sm">
                                <i class="bi bi-check-circle-fill me-1"></i> ДОБАВЛЕНО
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small fw-bold">ОТКУДА:</span>
                            <span class="text-dark small fw-bold"><?php echo e($parcel['s_name']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <span class="text-muted small fw-bold">КУДА:</span>
                            <span class="text-dark small fw-bold"><?php echo e($parcel['r_name']); ?></span>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-4">История перемещений</h5>
                    <?php if($history): ?>
                        <div class="timeline-simple">
                            <?php foreach($history as $idx => $st): ?>
                                <div class="timeline-item <?php echo $idx === 0 ? 'active' : ''; ?>">
                                    <div class="time">
                                        <?php
                                        if (preg_match('/\[(\d{2}\.\d{2}\.\d{4}\s\d{2}:\d{2})\]/', $st['status_text'], $matches)) {
                                            echo str_replace(' ', '<br>', $matches[1]);
                                        } elseif(!empty($st['created_at'])) {
                                            echo date('d.m.Y<br>H:i', strtotime($st['created_at']));
                                        } else {
                                            echo "---";
                                        }
                                        ?>
                                    </div>
                                    <div class="dot"></div>
                                    <div class="text">
                                        <div class="fw-bold <?php echo $idx === 0 ? 'text-primary' : 'text-dark'; ?>">
                                            <?php echo e(trim(preg_replace('/\[\d{2}\.\d{2}\.\d{4}\s\d{2}:\d{2}\]/', '', $st['status_text']))); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted italic">Информация о статусах временно недоступна.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.btn-white { background: #fff; color: var(--primary); border: none; }
.btn-white:hover { background: #f8f9fa; }
.timeline-simple { position: relative; padding-left: 0; }
.timeline-item { display: flex; margin-bottom: 25px; position: relative; }
.timeline-item .time { width: 70px; font-size: 11px; font-weight: 800; color: #999; text-align: right; padding-right: 15px; line-height: 1.2; }
.timeline-item .dot { width: 12px; height: 12px; background: #e0e0e0; border-radius: 50%; margin-top: 4px; z-index: 2; position: relative; transition: 0.3s; }
.timeline-item.active .dot { background: var(--primary); box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.2); }
.timeline-item .text { padding-left: 15px; flex: 1; font-size: 14px; }
.timeline-item::before { content: ''; position: absolute; left: 75px; top: 16px; bottom: -20px; width: 2px; background: #f0f0f0; }
.timeline-item:last-child::before { display: none; }
</style>

<?php include __DIR__ . '/footer.php'; ?>
