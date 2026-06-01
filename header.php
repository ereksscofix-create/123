<?php
// header.php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --rainbow-gradient: linear-gradient(to right, #ff2400, #e81d1d, #e8b21d, #1de840, #1ddde8, #2b1de8, #dd00f3);
        }
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
            border-bottom: 3px solid transparent;
            border-image: var(--rainbow-gradient);
            border-image-slice: 1;
        }
        .rainbow-text {
            background: var(--rainbow-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,.05);
        }
        .btn-rainbow {
            background: var(--rainbow-gradient);
            color: white;
            border: none;
            transition: opacity 0.3s;
        }
        .btn-rainbow:hover {
            opacity: 0.9;
            color: white;
        }
        .nav-link.active {
            font-weight: bold;
            color: #000 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg mb-4">
        <div class="container">
            <a class="navbar-brand rainbow-text" href="dashboard.php">🌈 <?= SITE_NAME ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><?= t('Главная', 'Басты') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="trip_add.php"><?= t('Новое путешествие', 'Жаңа саяхат') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="map.php"><?= t('Карта', 'Карта') ?></a></li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <?= $lang === 'ru' ? '🇷🇺 RU' : '🇰🇿 KK' ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-menu-item p-2 text-decoration-none d-block text-dark" href="?lang=ru">🇷🇺 Русский</a></li>
                            <li><a class="dropdown-menu-item p-2 text-decoration-none d-block text-dark" href="?lang=kk">🇰🇿 Қазақша</a></li>
                        </ul>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><?= t('Выход', 'Шығу') ?></a></li>
                    <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php"><?= t('Вход', 'Кіру') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="register.php"><?= t('Регистрация', 'Тіркелу') ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container pb-5">
