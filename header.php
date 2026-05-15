<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0">
    <meta name="theme-color" content="#4361ee">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EHPST">
    <title><?php echo $page_title ?? 'EHPST - Почтовая система'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3f37c9;
            --primary-light: #4895ef;
            --success: #06c755;
            --warning: #ff9500;
            --danger: #ff3b30;
            --text-dark: #1a1a2e;
            --text-gray: #6b7280;
            --border-color: #e5e7eb;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
        }

        * {
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: var(--bg-white);
            border-bottom: 1px solid var(--border-color);
            padding: 0.75rem 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary) !important;
            letter-spacing: -0.5px;
        }

        .navbar-brand i {
            margin-right: 0.5rem;
        }

        .nav-link {
            font-weight: 500;
            color: var(--text-dark) !important;
            font-size: 0.95rem;
            transition: color 0.2s ease;
            padding: 0.5rem 1rem !important;
        }

        .nav-link:hover {
            color: var(--primary) !important;
        }

        .dropdown-menu {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: var(--text-dark);
            border-radius: 8px;
            margin: 0.25rem 0.5rem;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: var(--primary);
            color: white;
            transform: translateX(4px);
        }

        /* ===== КОНТЕЙНЕР ===== */
        .container {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .main-content {
            min-height: 100vh;
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        /* ===== КАРТОЧКИ ===== */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            background: var(--bg-white);
            margin-bottom: 1.5rem;
        }

        .card:active {
            transform: translateY(2px);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .card-header {
            background: var(--bg-white);
            border: none;
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-body {
            padding: 1.25rem;
        }

        /* ===== КНОПКИ ===== */
        .btn {
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.75rem 1.25rem;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .btn:active {
            transform: scale(0.98);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.4);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #05a345;
        }

        .btn-outline-secondary {
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            background: var(--bg-white);
        }

        .btn-outline-secondary:hover {
            background: var(--bg-light);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            min-height: 36px;
            font-size: 0.85rem;
        }

        .btn-lg {
            padding: 1rem 2rem;
            min-height: 52px;
            font-size: 1rem;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* ===== ФОРМЫ ===== */
        .form-control,
        .form-select {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            min-height: 48px;
            font-family: inherit;
            transition: all 0.2s ease;
            background: var(--bg-white);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        /* ===== ALERT ===== */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }

        /* ===== БЕЙДЖИ ===== */
        .badge {
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.8rem;
        }

        /* ===== TEXT UTILITIES ===== */
        .text-muted {
            color: var(--text-gray) !important;
        }

        .text-primary {
            color: var(--primary) !important;
        }

        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: 800;
        }

        h2 {
            font-size: 1.75rem;
        }

        h3 {
            font-size: 1.5rem;
        }

        h4 {
            font-size: 1.25rem;
        }

        h5 {
            font-size: 1.1rem;
        }

        h6 {
            font-size: 1rem;
        }

        .small-text {
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        /* ===== SECTION PADDING ===== */
        .section {
            padding: 1.5rem 0;
        }

        .py-section {
            padding: 2rem 0;
        }

        /* ===== FOOTER ===== */
        footer {
            background: var(--bg-light);
            border-top: 1px solid var(--border-color);
            padding: 2rem 0;
            margin-top: 3rem;
            text-align: center;
        }

        footer p {
            color: var(--text-gray);
            margin: 0;
            font-size: 0.9rem;
        }

        /* ===== RESPONSIVENESS ===== */
        @media (max-width: 576px) {
            body {
                font-size: 15px;
            }

            h1 {
                font-size: 1.5rem;
            }

            h2 {
                font-size: 1.35rem;
            }

            h3 {
                font-size: 1.2rem;
            }

            .main-content {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .btn {
                width: 100%;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                width: 100%;
            }

            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-lightning-charge-fill"></i>EHPST
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="track.php">Отследить</a>
                    </li>
                    <?php
                    $currUser = currentUser();
                    if($currUser):
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($currUser['name'] ?: $currUser['login']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid me-2"></i>Дашборд</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Выйти</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Войти</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm px-3" href="register.php" style="min-height: auto; padding: 0.4rem 1rem;">Регистрация</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="container">
