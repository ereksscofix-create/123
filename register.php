<?php
// register.php - Регистрация обычных пользователей
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');

    if (empty($login) || empty($password) || empty($name)) {
        $error = "Заполните все поля.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE login = :l");
            $stmt->execute(['l' => $login]);
            if ($stmt->fetch()) {
                $error = "Логин уже занят.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (login, password, name, role) VALUES (:l, :p, :n, 'recipient')");
                $stmt->execute(['l' => $login, 'p' => $hash, 'n' => $name]);
                $message = "Регистрация успешна! Теперь вы можете войти.";
            }
        } catch (PDOException $e) { $error = "Ошибка: " . $e->getMessage(); }
    }
}

$page_title = "Регистрация — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-md-5">
        <div class="card shadow-lg border-0 rounded-4">
            <div class="card-header bg-primary text-white p-4 text-center">
                <h4 class="mb-0 fw-bold">Создать аккаунт</h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ваше ФИО</label>
                        <input type="text" name="name" class="form-control" required placeholder="Иванов Иван Иванович">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Логин</label>
                        <input type="text" name="login" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Пароль</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">ЗАРЕГИСТРИРОВАТЬСЯ</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-muted small">Уже есть аккаунт? Войти</a>
                </div>
                <hr>
                <div class="text-center">
                    <a href="register_worker.php" class="text-muted x-small">Регистрация для сотрудников</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
