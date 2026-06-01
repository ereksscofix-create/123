<?php
require_once 'header.php';
check_auth();

// Fetch trips
$stmt = $pdo->query("SELECT * FROM trips ORDER BY start_date DESC");
$all_trips = $stmt->fetchAll();

$upcoming = [];
$past = [];
$now = date('Y-m-d');

foreach ($all_trips as $trip) {
    if ($trip['start_date'] > $now) {
        $upcoming[] = $trip;
    } else {
        $past[] = $trip;
    }
}

// Next trip for countdown
$next_trip = !empty($upcoming) ? end($upcoming) : null;
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card p-4 text-center">
            <h1><?= t('Привет, ', 'Сәлем, ') . htmlspecialchars($u['name']) ?>! 🌈</h1>
            <p class="lead text-muted"><?= t('Куда отправимся в следующий раз?', 'Келесі жолы қайда барамыз?') ?></p>

            <?php if ($next_trip): ?>
                <div class="mt-3">
                    <h3><?= t('До следующего приключения в ', 'Келесі саяхатқа дейін ') ?> <strong><?= htmlspecialchars($next_trip['title']) ?></strong>:</h3>
                    <div id="countdown" class="display-4 fw-bold rainbow-text" data-date="<?= $next_trip['start_date'] ?>">--:--:--:--</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <h3 class="mb-3"><?= t('Наши планы', 'Біздің жоспарлар') ?></h3>
        <?php if (empty($upcoming)): ?>
            <div class="alert alert-info"><?= t('Пока нет запланированных поездок.', 'Әзірге жоспарланған саяхаттар жоқ.') ?> <a href="trip_add.php"><?= t('Добавить?', 'Қосу керек пе?') ?></a></div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($upcoming as $trip): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($trip['title']) ?></h5>
                                <p class="card-text text-muted small"><i class="bi bi-calendar"></i> <?= $trip['start_date'] ?> — <?= $trip['end_date'] ?></p>
                                <a href="trip_view.php?id=<?= $trip['id'] ?>" class="btn btn-outline-primary btn-sm"><?= t('Посмотреть', 'Көру') ?></a>
                                <a href="trip_edit.php?id=<?= $trip['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3 class="mt-4 mb-3"><?= t('Где мы были', 'Біз қайда болдық') ?></h3>
        <div class="row">
            <?php if (empty($past)): ?>
                <div class="col-12"><p class="text-muted"><?= t('История путешествий пока пуста.', 'Саяхат тарихы әлі бос.') ?></p></div>
            <?php else: ?>
                <?php foreach ($past as $trip): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 opacity-75">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($trip['title']) ?></h5>
                                <p class="card-text text-muted small"><?= $trip['start_date'] ?></p>
                                <a href="trip_view.php?id=<?= $trip['id'] ?>" class="btn btn-light btn-sm"><?= t('Вспомнить', 'Еске алу') ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 mb-4">
            <h5><i class="bi bi-geo-alt"></i> <?= t('Статистика', 'Статистика') ?></h5>
            <hr>
            <p><?= t('Всего поездок: ', 'Барлығы саяхат: ') ?> <strong><?= count($all_trips) ?></strong></p>
            <p><?= t('Посещено стран: ', 'Барған елдер: ') ?> <strong>
                <?php
                $stmt = $pdo->query("SELECT COUNT(DISTINCT country_id) FROM trip_countries");
                echo $stmt->fetchColumn();
                ?>
            </strong></p>
        </div>
        <div class="d-grid">
            <a href="trip_add.php" class="btn btn-rainbow btn-lg"><i class="bi bi-plus-lg"></i> <?= t('Новое путешествие', 'Жаңа саяхат') ?></a>
        </div>
    </div>
</div>

<script>
    const countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        const targetDate = new Date(countdownEl.dataset.date + 'T00:00:00').getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0) {
                countdownEl.innerHTML = "00:00:00:00";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            countdownEl.innerHTML = `${days}д ${hours}ч ${minutes}м ${seconds}с`;
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
    }
</script>

<?php include 'footer.php'; ?>
