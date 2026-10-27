<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$user = require_auth();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Операция не найдена.'); }
$isAdmin = ($user['role'] ?? 'user') === 'admin';
$stmt = db()->prepare('SELECT t.id, t.user_id, t.type, t.amount, t.transaction_date, t.description, t.source, t.external_id, t.created_at,
    c.name AS category, u.full_name AS owner_name, u.email AS owner_email
    FROM transactions t JOIN categories c ON c.id = t.category_id JOIN users u ON u.id = t.user_id
    WHERE t.id = :id AND (t.user_id = :user_id OR :is_admin = 1) LIMIT 1');
$stmt->execute(['id' => $id, 'user_id' => (int)$user['id'], 'is_admin' => $isAdmin ? 1 : 0]);
$transaction = $stmt->fetch();
if (!$transaction) { http_response_code(404); exit('Операция не найдена.'); }
?>
<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Операция #<?= (int)$transaction['id'] ?> — FinanceAssistant</title><link rel="stylesheet" href="auth.css"></head>
<body><div class="dashboard"><aside class="sidebar"><div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div><nav><a href="dashboard.php">Личный кабинет</a><a class="active" href="transactions.php">Операции</a><?php if ($isAdmin): ?><a href="admin-transactions.php">Администрирование</a><?php endif; ?><a href="logout.php">Выйти</a></nav></aside><main class="main">
<div class="topbar"><div><h1>Операция #<?= (int)$transaction['id'] ?></h1><p>Подробная информация о финансовой записи</p></div><a class="action-link" href="<?= $isAdmin ? 'admin-transactions.php' : 'transactions.php' ?>">← К списку</a></div>
<section class="detail-grid"><article class="stat"><div class="label">Тип</div><div class="value"><?= $transaction['type'] === 'income' ? 'Доход' : 'Расход' ?></div></article><article class="stat"><div class="label">Сумма</div><div class="value"><?= e(number_format((float)$transaction['amount'], 2, ',', ' ')) ?> ₽</div></article><article class="stat"><div class="label">Дата операции</div><div class="value"><?= e(date('d.m.Y', strtotime($transaction['transaction_date']))) ?></div></article><article class="stat"><div class="label">Категория</div><div class="value"><?= e($transaction['category']) ?></div></article><article class="stat"><div class="label">Описание</div><div class="value"><?= e($transaction['description'] ?: '—') ?></div></article><article class="stat"><div class="label">Источник</div><div class="value"><?= $transaction['source'] === 'csv' ? 'Импорт CSV' : 'Вручную' ?></div></article>
<?php if ($isAdmin): ?><article class="stat"><div class="label">Владелец записи</div><div class="value"><?= e($transaction['owner_name']) ?> · <?= e($transaction['owner_email']) ?></div></article><?php endif; ?>
<?php if ($transaction['external_id']): ?><article class="stat"><div class="label">Внешний идентификатор</div><div class="value"><?= e($transaction['external_id']) ?></div></article><?php endif; ?></section></main></div></body></html>
