<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
require_guest();

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$validationErrors, $fullName, $email, $password] = validate_registration($_POST);
    $errors = $validationErrors;

    if (!$errors && !verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия формы устарела. Обновите страницу и попробуйте снова.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Пользователь с таким email уже зарегистрирован.';
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (:full_name, :email, :password_hash)');
        $stmt->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
        set_flash('success', 'Регистрация завершена. Теперь войдите в систему.');
        header('Location: login.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Регистрация — FinanceAssistant</title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <section class="auth-info">
      <div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div>
      <h1>Начните вести бюджет осознанно.</h1>
      <p>Аккаунт нужен, чтобы хранить личные операции отдельно и в дальнейшем подключить бюджеты, CSV-импорт и аналитику.</p>
      <ul class="feature-list">
        <li><span>✓</span> Индивидуальные финансовые данные</li>
        <li><span>✓</span> Безопасное хранение пароля</li>
        <li><span>✓</span> Подготовка к REST API и React-клиенту</li>
      </ul>
    </section>

    <section class="auth-form">
      <a class="back" href="login.php">← Уже есть аккаунт</a>
      <h2>Регистрация</h2>
      <p class="subtitle">Создайте новый аккаунт</p>
      <?php if ($errors): ?>
        <div class="alert error"><ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label for="full_name">Имя</label>
          <input id="full_name" name="full_name" type="text" autocomplete="name" value="<?= e($fullName) ?>" required maxlength="120">
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" value="<?= e($email) ?>" required maxlength="255">
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="72">
          <div class="password-hint">Минимум 8 символов, обязательно буква и цифра.</div>
        </div>
        <div class="field">
          <label for="password_confirm">Повторите пароль</label>
          <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="8" maxlength="72">
        </div>
        <button class="btn" type="submit">Зарегистрироваться</button>
      </form>
      <div class="switch">Уже зарегистрированы? <a href="login.php">Войти</a></div>
    </section>
  </div>
</div>
</body>
</html>
