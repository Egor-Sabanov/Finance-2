<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$user = require_auth();

$search = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$categoryId = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT) ?: null;
$dateFrom = (string)($_GET['from'] ?? '');
$dateTo = (string)($_GET['to'] ?? '');
$validDate = static fn(string $date): bool => $date === '' || (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
if (!in_array($type, ['', 'income', 'expense'], true)) $type = '';
if (!$validDate($dateFrom)) $dateFrom = '';
if (!$validDate($dateTo)) $dateTo = '';
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

$where = ['t.user_id = :user_id'];
$params = ['user_id' => (int)$user['id']];
if ($search !== '') {
    $where[] = '(t.description LIKE :search_description OR c.name LIKE :search_category)';
    $params['search_description'] = '%' . $search . '%';
    $params['search_category'] = '%' . $search . '%';
}
if ($type !== '') { $where[] = 't.type = :type'; $params['type'] = $type; }
if ($categoryId !== null) { $where[] = 't.category_id = :category_id'; $params['category_id'] = $categoryId; }
if ($dateFrom !== '') { $where[] = 't.transaction_date >= :date_from'; $params['date_from'] = $dateFrom; }
if ($dateTo !== '') { $where[] = 't.transaction_date <= :date_to'; $params['date_to'] = $dateTo; }
$stmt = db()->prepare('SELECT t.id, t.type, t.amount, t.transaction_date, t.description, c.name AS category
    FROM transactions t JOIN categories c ON c.id = t.category_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY t.transaction_date DESC, t.id DESC');
$stmt->execute($params);
$transactions = $stmt->fetchAll();
$categoryStmt = db()->prepare('SELECT id, name FROM categories WHERE user_id = :user_id ORDER BY name');
$categoryStmt->execute(['user_id' => (int)$user['id']]);
$categories = $categoryStmt->fetchAll();
?>
<!doctype html>
<html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Операции — FinanceAssistant</title><link rel="stylesheet" href="auth.css"></head>
<body><div class="dashboard">
  <aside class="sidebar"><div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div><nav><a href="dashboard.php">Личный кабинет</a><a class="active" href="transactions.php">Операции</a><?php if (($user['role'] ?? 'user') === 'admin'): ?><a href="admin-transactions.php">Администрирование</a><?php endif; ?><a href="logout.php">Выйти</a></nav></aside>
  <main class="main"><div class="topbar"><div><h1>Мои операции</h1><p>Детали доступны только владельцу записи и администратору.</p></div><a class="action-link" href="dashboard.php">← В кабинет</a></div>
    <form class="filters" method="get">
      <div class="field"><label for="q">Поиск по описанию или категории</label><input id="q" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Например, продукты"></div>
      <div class="field"><label for="type">Тип</label><select id="type" name="type"><option value="">Все</option><option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Доход</option><option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Расход</option></select></div>
      <div class="field"><label for="category">Категория</label><select id="category" name="category"><option value="">Все категории</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="from">Дата с</label><input id="from" name="from" type="date" value="<?= e($dateFrom) ?>"></div>
      <div class="field"><label for="to">Дата по</label><input id="to" name="to" type="date" value="<?= e($dateTo) ?>"></div>
      <div class="filter-actions"><button class="btn" type="submit">Найти</button><a href="transactions.php">Сбросить</a></div>
    </form>
    <section class="panel"><h2>Найдено: <?= count($transactions) ?></h2><div class="table-wrap"><table><thead><tr><th>Дата</th><th>Тип</th><th>Категория</th><th>Сумма</th><th>Описание</th><th></th></tr></thead><tbody>
    <?php foreach ($transactions as $transaction): ?><tr><td><?= e(date('d.m.Y', strtotime($transaction['transaction_date']))) ?></td><td><?= $transaction['type'] === 'income' ? 'Доход' : 'Расход' ?></td><td><?= e($transaction['category']) ?></td><td><?= e(number_format((float)$transaction['amount'], 2, ',', ' ')) ?> ₽</td><td><?= e($transaction['description'] ?? '—') ?></td><td><a href="transaction.php?id=<?= (int)$transaction['id'] ?>">Подробнее</a></td></tr><?php endforeach; ?>
    <?php if (!$transactions): ?><tr><td colspan="6" class="empty">Операций по заданным условиям нет.</td></tr><?php endif; ?></tbody></table></div></section>
  </main></div></body></html>
