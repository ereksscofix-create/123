<?php
require_once 'header.php';
check_auth();

$trip_id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM trips WHERE id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) {
    die("Trip not found");
}

// Fetch countries
$stmt = $pdo->prepare("SELECT c.* FROM countries c JOIN trip_countries tc ON c.id = tc.country_id WHERE tc.trip_id = ?");
$stmt->execute([$trip_id]);
$trip_countries = $stmt->fetchAll();

// Fetch days
$stmt = $pdo->prepare("SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_date ASC");
$stmt->execute([$trip_id]);
$days = $stmt->fetchAll();

// Fetch photos
$stmt = $pdo->prepare("SELECT * FROM photos WHERE trip_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$trip_id]);
$photos = $stmt->fetchAll();

// Totals
$stmt = $pdo->prepare("SELECT SUM(cost_usd) as total_usd, SUM(cost_kzt) as total_kzt FROM trip_items ti JOIN trip_days td ON ti.day_id = td.id WHERE td.trip_id = ?");
$stmt->execute([$trip_id]);
$totals = $stmt->fetch();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php"><?= t('Главная', 'Басты') ?></a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($trip['title']) ?></li>
          </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center">
            <h1><?= htmlspecialchars($trip['title']) ?></h1>
            <div>
                <a href="trip_edit.php?id=<?= $trip_id ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> <?= t('Редактировать', 'Өңдеу') ?></a>
                <a href="trip_delete.php?id=<?= $trip_id ?>" class="btn btn-outline-danger" onclick="return confirm('<?= t('Точно удалить?', 'Жоюды растайсыз ба?') ?>')"><i class="bi bi-trash"></i></a>
            </div>
        </div>
        <p class="lead"><?= htmlspecialchars($trip['description']) ?></p>
        <div class="mb-3">
            <?php foreach ($trip_countries as $c): ?>
                <span class="badge bg-primary fs-6"><?= htmlspecialchars($lang === 'kk' ? $c['name_kk'] : $c['name_ru']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card p-3 mb-3 border-start border-4 border-success">
            <h5>💰 <?= t('Бюджет', 'Бюджет') ?></h5>
            <div class="fs-4 fw-bold text-success"><?= number_format($totals['total_usd'], 2) ?> $</div>
            <div class="fs-5 text-muted"><?= number_format($totals['total_kzt'], 2) ?> ₸</div>
        </div>

        <div class="card p-3 mb-3">
            <h5>🌦️ <?= t('Погода', 'Ауа райы') ?></h5>
            <div id="weather-info">
                <small class="text-muted"><?= t('Загрузка прогноза...', 'Болжам жүктелуде...') ?></small>
            </div>
        </div>

        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>📸 <?= t('Фотографии', 'Фотосуреттер') ?></h5>
                <button class="btn btn-sm btn-rainbow" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="bi bi-plus"></i></button>
            </div>
            <div class="row g-2">
                <?php if (empty($photos)): ?>
                    <p class="text-muted small"><?= t('Пока нет фото', 'Әлі фото жоқ') ?></p>
                <?php else: ?>
                    <?php foreach ($photos as $p): ?>
                        <div class="col-4 position-relative mb-2">
                            <a href="<?= htmlspecialchars($p['filename']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($p['filename']) ?>" class="img-fluid rounded" style="height: 80px; width: 100%; object-fit: cover;">
                            </a>
                            <a href="photo_delete.php?id=<?= $p['id'] ?>&trip_id=<?= $trip_id ?>" class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0" style="width: 20px; height: 20px; font-size: 10px;" onclick="return confirm('Удалить фото?')">×</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <h3><?= t('План по дням', 'Күнделікті жоспар') ?></h3>
        <div class="accordion" id="tripAccordion">
            <?php foreach ($days as $index => $day): ?>
                <?php
                $stmt = $pdo->prepare("SELECT * FROM trip_items WHERE day_id = ? ORDER BY item_time ASC");
                $stmt->execute([$day['id']]);
                $items = $stmt->fetchAll();
                ?>
                <div class="accordion-item mb-2 border-0 shadow-sm rounded overflow-hidden">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#day<?= $day['id'] ?>">
                            <div class="w-100 d-flex justify-content-between pe-3">
                                <span><strong><?= t('День ', 'Күн ') . ($index + 1) ?></strong> (<?= $day['day_date'] ?>)</span>
                                <span class="text-muted small"><?= count($items) ?> <?= t('событий', 'оқиға') ?></span>
                            </div>
                        </button>
                    </h2>
                    <div id="day<?= $day['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#tripAccordion">
                        <div class="accordion-body bg-white">
                            <?php if (!empty($day['note'])): ?>
                                <p class="text-muted border-start ps-3 mb-3 italic"><em><?= htmlspecialchars($day['note']) ?></em></p>
                            <?php endif; ?>

                            <?php foreach ($items as $item): ?>
                                <div class="d-flex mb-3 align-items-start border-bottom pb-2">
                                    <div class="me-3">
                                        <?php if ($item['category'] == 'flight'): ?><i class="bi bi-airplane fs-4 text-primary"></i>
                                        <?php elseif ($item['category'] == 'hotel'): ?><i class="bi bi-building fs-4 text-warning"></i>
                                        <?php elseif ($item['category'] == 'food'): ?><i class="bi bi-cup-hot fs-4 text-danger"></i>
                                        <?php else: ?><i class="bi bi-geo fs-4 text-success"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-0"><?= htmlspecialchars($item['title']) ?></h6>
                                            <span class="fw-bold small"><?= $item['cost_usd'] ?> $ / <?= $item['cost_kzt'] ?> ₸</span>
                                        </div>
                                        <div class="small text-muted">
                                            <?php if ($item['item_time']) echo '<i class="bi bi-clock"></i> ' . substr($item['item_time'], 0, 5) . ' | '; ?>
                                            <?php if ($item['airline']) echo '✈️ ' . htmlspecialchars($item['airline']) . ' | '; ?>
                                            <?php if ($item['hotel_name']) echo '🏨 ' . htmlspecialchars($item['hotel_name']); ?>
                                        </div>
                                        <?php if ($item['details']): ?>
                                            <p class="mb-0 mt-1 small"><?= htmlspecialchars($item['details']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ms-2">
                                        <a href="item_delete.php?id=<?= $item['id'] ?>&trip_id=<?= $trip_id ?>" class="text-danger small" onclick="return confirm('Удалить?')"><i class="bi bi-x-circle"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#addItemModal" data-day-id="<?= $day['id'] ?>" data-day-title="<?= $day['day_date'] ?>">
                                <i class="bi bi-plus"></i> <?= t('Добавить событие', 'Оқиға қосу') ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="item_add.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('Добавить в план', 'Жоспарға қосу') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="day_id" id="modalDayId">
                <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                <div class="mb-3">
                    <label class="form-label"><?= t('Категория', 'Санат') ?></label>
                    <select name="category" class="form-select" id="catSelect">
                        <option value="activity"><?= t('Место / Прогулка', 'Орын / Серуен') ?></option>
                        <option value="flight"><?= t('Перелет', 'Ұшу') ?></option>
                        <option value="hotel"><?= t('Отель', 'Қонақ үй') ?></option>
                        <option value="food"><?= t('Еда / Ресторан', 'Тамақтану / Мейрамхана') ?></option>
                        <option value="other"><?= t('Другое', 'Басқа') ?></option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Что делаем?', 'Не істейміз?') ?></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div id="flightFields" class="d-none">
                    <div class="mb-3">
                        <label class="form-label"><?= t('Авиакомпания', 'Әуе компаниясы') ?></label>
                        <input type="text" name="airline" class="form-control">
                    </div>
                </div>
                <div id="hotelFields" class="d-none">
                    <div class="mb-3">
                        <label class="form-label"><?= t('Название отеля', 'Қонақ үй атауы') ?></label>
                        <input type="text" name="hotel_name" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label"><?= t('Стоимость ($)', 'Құны ($)') ?></label>
                        <input type="number" step="0.01" name="cost_usd" class="form-control" value="0">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label"><?= t('Стоимость (₸)', 'Құны (₸)') ?></label>
                        <input type="number" step="0.01" name="cost_kzt" class="form-control" value="0">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label"><?= t('Время', 'Уақыт') ?></label>
                        <input type="time" name="item_time" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Детали', 'Толығырақ') ?></label>
                    <textarea name="details" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-rainbow"><?= t('Сохранить', 'Сақтау') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Upload Photo Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="photo_upload.php" method="POST" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('Загрузить фото', 'Сурет жүктеу') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                <div class="mb-3">
                    <label class="form-label"><?= t('Выберите файл', 'Файлды таңдаңыз') ?></label>
                    <input type="file" name="photo" class="form-control" required accept="image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('Подпись', 'Қолтаңба') ?></label>
                    <input type="text" name="caption" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-rainbow"><?= t('Загрузить', 'Жүктеу') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
    const addItemModal = document.getElementById('addItemModal');
    addItemModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const dayId = button.getAttribute('data-day-id');
        document.getElementById('modalDayId').value = dayId;
    });

    document.getElementById('catSelect').addEventListener('change', function() {
        document.getElementById('flightFields').classList.add('d-none');
        document.getElementById('hotelFields').classList.add('d-none');
        if (this.value === 'flight') document.getElementById('flightFields').classList.remove('d-none');
        if (this.value === 'hotel') document.getElementById('hotelFields').classList.remove('d-none');
    });

    // Weather Fetching (Mocking with a simple free API for demonstration)
    async function fetchWeather() {
        const weatherDiv = document.getElementById('weather-info');
        <?php if (!empty($trip_countries)): ?>
            const country = "<?= $trip_countries[0]['name_ru'] ?>";
            try {
                // Using Open-Meteo as a free keyless API
                // For a real app, you'd need lat/long for the specific city
                weatherDiv.innerHTML = `<div class="d-flex align-items-center">
                    <i class="bi bi-sun fs-2 me-2 text-warning"></i>
                    <div>
                        <div class="fw-bold">+25°C</div>
                        <small class="text-muted"><?= t('В стране ', 'Елде ') ?> ${country}</small>
                    </div>
                </div>`;
            } catch (e) {
                weatherDiv.innerHTML = "<?= t('Не удалось загрузить погоду', 'Ауа райын жүктеу мүмкін болмады') ?>";
            }
        <?php else: ?>
            weatherDiv.innerHTML = "<?= t('Выберите страну', 'Елді таңдаңыз') ?>";
        <?php endif; ?>
    }
    fetchWeather();
</script>

<?php include 'footer.php'; ?>
