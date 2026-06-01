<?php
require_once 'config.php';
check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day_id = (int)$_POST['day_id'];
    $trip_id = (int)$_POST['trip_id'];
    $category = $_POST['category'];
    $title = trim($_POST['title']);
    $airline = trim($_POST['airline'] ?? '');
    $hotel = trim($_POST['hotel_name'] ?? '');
    $cost_usd = (float)$_POST['cost_usd'];
    $cost_kzt = (float)$_POST['cost_kzt'];
    $details = trim($_POST['details']);
    $time = $_POST['item_time'];

    $stmt = $pdo->prepare("INSERT INTO trip_items (day_id, category, title, airline, hotel_name, cost_usd, cost_kzt, details, item_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$day_id, $category, $title, $airline, $hotel, $cost_usd, $cost_kzt, $details, $time ? $time : null]);

    header("Location: trip_view.php?id=$trip_id");
    exit;
}
?>
