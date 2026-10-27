<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$admin = require_admin();
$search = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$isDate = static fn(string $date): bool => $date === '' || (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
if (!$isDate($from)) $from = '';
if (!$isDate($to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];
if (!in_array($type, ['', 'income', 'expense'], true)) $type = '';
$where = ['1 = 1']; $params = [];
if ($search !== '') {
    $where[] = '(u.full_name LIKE :owner_name OR u.email LIKE :owner_email OR c.name LIKE :category OR t.description LIKE :description OR CAST(t.id AS CHAR) = :transaction_id)';
    $like = '%' . $search . '%';
    $params += ['owner_name' => $like, 'owner_email' => $like, 'category' => $like, 'description' => $like, 'transaction_id' => $search];
}
if ($type !== '') { $where[] = 't.type = :type'; $params['type'] = $type; }
if ($from !== '') { $where[] = 't.transaction_date >= :date_from'; $params['date_from'] = $from; }
if ($to !== '') { $where[] = 't.transaction_date <= :date_to'; $params['date_to'] = $to; }
$stmt = db()->prepare('SELECT t.id, t.type, t.amount, t.transaction_date, t.description, c.name AS category, u.full_name AS owner_name, u.email AS owner_email
    FROM transactions t JOIN categories c ON c.id = t.category_id JOIN users u ON u.id = t.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY t.transaction_date DESC, t.id DESC');
$stmt->execute($params); $transactions = $stmt->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Администрирование — FinanceAssistant</title><link rel="stylesheet" href="auth.css"></head>
<body><div class="dashboard"><aside class="sidebar"><div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div><nav><a href="dashboard.php">Личный кабинет</a><a href="transactions.php">Мои операции</a><a class="active" href="admin-transactions.php">Администрирование</a><a href="logout.php">Выйти</a></nav></aside><main class="main">
<div class="topbar"><div><h1>Все финансовые записи</h1><p>Администратор: <?= e($admin['full_name']) ?> · доступ только для роли admin</p></div><a class="action-link" href="dashboard.php">← В кабинет</a></div>
<form class="filters" method="get"><div class="field"><label for="q">Поиск: владелец, email, категория, описание или ID</label><input id="q" name="q" value="<?= e($search) ?>" maxlength="100"></div><div class="field"><label for="type">Тип записи</label><select id="type" name="type"><option value="">Все</option><option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Доход</option><option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Расход</option></select></div><div class="field"><label for="from">Дата с</label><input id="from" name="from" type="date" value="<?= e($from) ?>"></div><div class="field"><label for="to">Дата по</label><input id="to" name="to" type="date" value="<?= e($to) ?>"></div><div class="filter-actions"><button class="btn" type="submit">Найти</button><a href="admin-transactions.php">Сбросить</a></div></form>
<section class="panel"><h2>Записей: <?= count($transactions) ?></h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Дата</th><th>Владелец</th><th>Тип</th><th>Категория</th><th>Сумма</th><th>Описание</th><th></th></tr></thead><tbody>
<?php foreach ($transactions as $row): ?><tr><td><?= (int)$row['id'] ?></td><td><?= e(date('d.m.Y', strtotime($row['transaction_date']))) ?></td><td><?= e($row['owner_name']) ?><br><small><?= e($row['owner_email']) ?></small></td><td><?= $row['type'] === 'income' ? 'Доход' : 'Расход' ?></td><td><?= e($row['category']) ?></td><td><?= e(number_format((float)$row['amount'], 2, ',', ' ')) ?> ₽</td><td><?= e($row['description'] ?: '—') ?></td><td><a href="transaction.php?id=<?= (int)$row['id'] ?>">Подробнее</a></td></tr><?php endforeach; ?>
<?php if (!$transactions): ?><tr><td colspan="8" class="empty">Записей по заданным условиям нет.</td></tr><?php endif; ?></tbody></table></div></section></main></div></body></html>
