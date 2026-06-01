<?php
require_once 'header.php';
check_auth();

$trip_id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) die("Trip not found");

$countries = $pdo->query("SELECT * FROM countries ORDER BY name_ru ASC")->fetchAll();
$stmt = $pdo->prepare("SELECT country_id FROM trip_countries WHERE trip_id = ?");
$stmt->execute([$trip_id]);
$selected_country_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];
    $selected_countries = $_POST['countries'] ?? [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE trips SET title = ?, description = ?, start_date = ?, end_date = ? WHERE id = ?");
        $stmt->execute([$title, $desc, $start, $end, $trip_id]);

        $pdo->prepare("DELETE FROM trip_countries WHERE trip_id = ?")->execute([$trip_id]);
        foreach ($selected_countries as $c_id) {
            $pdo->prepare("INSERT INTO trip_countries (trip_id, country_id) VALUES (?, ?)")->execute([$trip_id, $c_id]);
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
            <h3><?= t('Редактировать путешествие', 'Саяхатты өңдеу') ?></h3>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><?= t('Название', 'Атауы') ?></label>
                    <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($trip['title']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Описание', 'Сипаттама') ?></label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($trip['description']) ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= t('Дата начала', 'Басталу күні') ?></label>
                        <input type="date" name="start_date" class="form-control" required value="<?= $trip['start_date'] ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= t('Дата завершения', 'Аяқталу күні') ?></label>
                        <input type="date" name="end_date" class="form-control" required value="<?= $trip['end_date'] ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Страны', 'Елдер') ?></label>
                    <div class="row" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; margin: 0;">
                        <?php foreach ($countries as $c): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="countries[]" value="<?= $c['id'] ?>" id="c<?= $c['id'] ?>" <?= in_array($c['id'], $selected_country_ids) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="c<?= $c['id'] ?>">
                                        <?= htmlspecialchars($lang === 'kk' ? $c['name_kk'] : $c['name_ru']) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="trip_view.php?id=<?= $trip_id ?>" class="btn btn-light"><?= t('Отмена', 'Бас тарту') ?></a>
                    <button type="submit" class="btn btn-rainbow px-5"><?= t('Сохранить', 'Сақтау') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
