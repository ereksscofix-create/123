<?php
require_once 'header.php';
check_auth();

$countries = $pdo->query("SELECT * FROM countries ORDER BY name_ru ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $selected_countries = $_POST['countries'] ?? [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO trips (title, description, start_date, end_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $start, $end]);
        $trip_id = $pdo->lastInsertId();

        foreach ($selected_countries as $c_id) {
            $stmt = $pdo->prepare("INSERT INTO trip_countries (trip_id, country_id) VALUES (?, ?)");
            $stmt->execute([$trip_id, $c_id]);
        }

        // Auto-create days
        $begin = new DateTime($start);
        $finish = new DateTime($end);
        $finish->modify('+1 day');
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($begin, $interval, $finish);

        foreach ($period as $dt) {
            $stmt = $pdo->prepare("INSERT INTO trip_days (trip_id, day_date) VALUES (?, ?)");
            $stmt->execute([$trip_id, $dt->format('Y-m-d')]);
        }

        $pdo->commit();
        header("Location: trip_view.php?id=$trip_id");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card p-4">
            <h3><?= t('Планируем новое приключение', 'Жаңа саяхатты жоспарлау') ?></h3>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label" for="title"><?= t('Название (например: Япония 2026)', 'Атауы (мысалы: Жапония 2026)') ?></label>
                    <input type="text" name="title" id="title" class="form-control" required placeholder="Summer Trip 2025">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description"><?= t('Описание', 'Сипаттама') ?></label>
                    <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="start_date"><?= t('Дата начала', 'Басталу күні') ?></label>
                        <input type="date" name="start_date" id="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="end_date"><?= t('Дата завершения', 'Аяқталу күні') ?></label>
                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Страны', 'Елдер') ?></label>
                    <div class="row" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; margin: 0;">
                        <?php foreach ($countries as $c): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="countries[]" value="<?= $c['id'] ?>" id="c<?= $c['id'] ?>">
                                    <label class="form-check-label" for="c<?= $c['id'] ?>">
                                        <?= htmlspecialchars($lang === 'kk' ? $c['name_kk'] : $c['name_ru']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-light"><?= t('Отмена', 'Бас тарту') ?></a>
                    <button type="submit" class="btn btn-rainbow px-5"><?= t('Создать', 'Қазу') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
