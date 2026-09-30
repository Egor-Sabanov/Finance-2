<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$user = require_auth();
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Личный кабинет — FinanceAssistant</title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div>
    <nav>
      <a class="active" href="dashboard.php">Главная</a>
      <a href="#">Операции</a>
      <a href="#">Категории</a>
      <a href="#">Бюджет</a>
      <a href="#">Импорт CSV</a>
      <a href="#">Отчёты</a>
      <a href="#">Уведомления</a>
      <a href="#">Настройки</a>
    </nav>
  </aside>
  <main class="main">
    <div class="topbar">
      <div>
        <h1>Привет, <?= e($user['full_name']) ?> 👋</h1>
        <p>Это рабочая авторизация первой реализации проекта.</p>
      </div>
      <div class="profile">
        <div class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
        <a class="logout" href="logout.php">Выйти</a>
      </div>
    </div>
    <section class="stats">
      <article class="stat"><div class="label">Аккаунт</div><div class="value ok">Активен</div></article>
      <article class="stat"><div class="label">Email</div><div class="value" style="font-size:20px"><?= e($user['email']) ?></div></article>
      <article class="stat"><div class="label">Зарегистрирован</div><div class="value" style="font-size:20px"><?= e(date('d.m.Y H:i', strtotime($user['created_at']))) ?></div></article>
    </section>
  </main>
</div>
</body>
</html>
