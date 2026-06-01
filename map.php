<?php
require_once 'header.php';
check_auth();
?>
<div class="row">
    <div class="col-12">
        <div class="card p-4">
            <h3><i class="bi bi-map"></i> <?= t('Карта наших путешествий', 'Саяхаттарымыздың картасы') ?></h3>
            <p class="text-muted"><?= t('Здесь будут отмечены все страны, в которых мы побывали!', 'Мұнда біз болған барлық елдер белгіленеді!') ?></p>
            <div id="map" style="height: 600px; border-radius: 15px;"></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    const map = L.map('map').setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Fetch visited countries from DB
    <?php
    $stmt = $pdo->query("SELECT DISTINCT c.name_ru, c.iso_code FROM countries c JOIN trip_countries tc ON c.id = tc.country_id");
    $visited = $stmt->fetchAll();
    ?>

    const visited = <?= json_encode($visited) ?>;

    // In a real app, we would use GeoJSON to highlight countries.
    // For now, we'll put markers on some major capitals of visited countries as a placeholder
    // or just list them. Let's add markers for simplicity in this demo.

    const coords = {
        'KZ': [48.0196, 66.9237],
        'RU': [61.5240, 105.3188],
        'JP': [36.2048, 138.2529],
        'AU': [-25.2744, 133.7751],
        'CN': [35.8617, 104.1954],
        'PH': [12.8797, 121.7740],
        'TH': [15.8700, 100.9925],
        'FR': [46.2276, 2.2137],
        'IT': [41.8719, 12.5674],
        'US': [37.0902, -95.7129],
        'TR': [38.9637, 35.2433]
    };

    visited.forEach(country => {
        if (coords[country.iso_code]) {
            L.marker(coords[country.iso_code])
                .addTo(map)
                .bindPopup(country.name_ru)
                .openPopup();
        }
    });
</script>

<?php include 'footer.php'; ?>
