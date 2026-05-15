<?php
// debug_session.php
session_start();

echo "<h1>Session Debug</h1>";
echo "Session ID: " . session_id() . "<br>";

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
    echo "Counter initialized to 1. Refresh page to see it increment.<br>";
} else {
    $_SESSION['test_counter']++;
    echo "Counter value: " . $_SESSION['test_counter'] . "<br>";
}

echo "<h2>Session Data:</h2><pre>";
print_r($_SESSION);
echo "</pre>";

echo "<hr><a href='login.php'>Go to Login</a>";
?>
