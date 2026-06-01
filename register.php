<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $name = trim($_POST['name']);
    $password = $_POST['password'];
    $invite = trim($_POST['invite']);

    if ($invite !== INVITE_CODE) {
        $error = t('Неверный код приглашения', 'Қате шақыру коды');
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            $error = t('Этот логин уже занят', 'Бұл логин бос емес');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (login, name, password) VALUES (?, ?, ?)");
            $stmt->execute([$login, $name, $hashed]);
            $success = t('Регистрация прошла успешно! Теперь вы можете войти.', 'Тіркеу сәтті өтті! Енді жүйеге кіре аласыз.');
        }
    }
}

include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card p-4">
            <h2 class="text-center mb-4"><?= t('Регистрация', 'Тіркелу') ?></h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label" for="name"><?= t('Имя', 'Есім') ?></label>
                    <input type="text" name="name" id="name" class="form-control" required autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="login"><?= t('Логин', 'Логин') ?></label>
                    <input type="text" name="login" id="login" class="form-control" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password"><?= t('Пароль', 'Құпия сөз') ?></label>
                    <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="invite"><?= t('Код приглашения', 'Шақыру коды') ?></label>
                    <input type="text" name="invite" id="invite" class="form-control" required>
                    <div class="form-text"><?= t('Спросите у мужа :)', 'Күйеуіңізден сұраңыз :)') ?></div>
                </div>
                <button type="submit" class="btn btn-rainbow w-100"><?= t('Зарегистрироваться', 'Тіркелу') ?></button>
            </form>
            <?php endif; ?>
            <div class="mt-3 text-center">
                <a href="login.php" class="text-decoration-none"><?= t('Уже есть аккаунт? Войдите', 'Аккаунт бар ма? Кіріңіз') ?></a>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
