<?php
// login.php
ob_start(); // Предотвращает ошибки "headers already sent"
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Если уже залогинен, отправляем в дашборд
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $message = "Пожалуйста, заполните все поля!";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE login = :login LIMIT 1");
            $stmt->execute(['login' => $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Очищаем сессию перед входом для безопасности
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];

                header("Location: dashboard.php");
                exit;
            } else {
                $message = "Неверный логин или пароль!";
            }
        } catch (PDOException $e) {
            $message = "Ошибка базы данных. Пожалуйста, попробуйте позже.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$page_title = "Вход - EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="col-12 col-sm-8 col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white text-center py-4 border-0" style="border-radius: 12px 12px 0 0;">
                <h3 class="mb-0 fw-bold">С возвращением!</h3>
                <p class="mb-0 opacity-75">Войдите в свой аккаунт EHPST</p>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-danger border-0">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo e($message); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Логин</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" name="login" class="form-control border-start-0" placeholder="Ваш логин" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Пароль</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" name="password" class="form-control border-start-0" placeholder="Ваш пароль" required>
                        </div>
                        <div class="text-end mt-2">
                            <a href="forgot_password.php" class="small text-decoration-none">Забыли пароль?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm mt-3">Войти в систему</button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <span class="text-muted">Нет аккаунта?</span>
                    <a href="register.php" class="fw-bold text-decoration-none ms-1">Зарегистрироваться</a>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="index.php" class="text-muted text-decoration-none small"><i class="bi bi-house-door me-1"></i> На главную</a>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/footer.php';
ob_end_flush();
?>
