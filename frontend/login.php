<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
require_guest();

$errors = [];
$email = '';
$flash = pull_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$validationErrors, $email, $password] = validate_login($_POST);
    $errors = $validationErrors;

    if (!$errors && !verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия формы устарела. Обновите страницу и попробуйте снова.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Неверный email или пароль.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход — FinanceAssistant</title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-card">
    <section class="auth-info">
      <div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div>
      <h1>Ваши финансы под контролем.</h1>
      <p>Личный финансовый помощник для учета доходов и расходов, планирования бюджета и анализа финансов.</p>
      <ul class="feature-list">
        <li><span>✓</span> Категории доходов и расходов</li>
        <li><span>✓</span> Будущий импорт банковских выписок CSV</li>
        <li><span>✓</span> Бюджеты и лимиты по категориям</li>
        <li><span>✓</span> Отчеты, диаграммы и уведомления</li>
      </ul>
    </section>

    <section class="auth-form">
      <a class="back" href="../index.php">← На главную</a>
      <h2>Вход</h2>
      <p class="subtitle">Войдите в личный кабинет</p>
      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="alert error"><ul class="errors"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" value="<?= e($email) ?>" required maxlength="255">
        </div>
        <div class="field">
          <label for="password">Пароль</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn" type="submit">Войти</button>
      </form>
      <div class="switch">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></div>
    </section>
  </div>
</div>
</body>
</html>
