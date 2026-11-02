<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$admin = require_admin();
$search = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$status = (string)($_GET['status'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$sort = (string)($_GET['sort'] ?? 'date');
$direction = strtolower((string)($_GET['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$statuses = ['planned' => 'Запланирована', 'completed' => 'Выполнена', 'cancelled' => 'Отменена'];
$isDate = static fn(string $date): bool => $date === '' || (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
if (!$isDate($from)) $from = '';
if (!$isDate($to)) $to = '';
if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];
if (!in_array($type, ['', 'income', 'expense'], true)) $type = '';
if (!array_key_exists($status, array_merge(['' => ''], $statuses))) $status = '';
$sortColumns = ['date' => 't.transaction_date', 'amount' => 't.amount', 'owner' => 'u.full_name', 'status' => 't.status', 'id' => 't.id'];
if (!isset($sortColumns[$sort])) $sort = 'date';
$where = ['1 = 1']; $params = [];
if ($search !== '') {
    $where[] = '(u.full_name LIKE :owner_name OR u.email LIKE :owner_email OR c.name LIKE :category OR t.description LIKE :description OR CAST(t.id AS CHAR) = :transaction_id)';
    $like = '%' . $search . '%';
    $params += ['owner_name' => $like, 'owner_email' => $like, 'category' => $like, 'description' => $like, 'transaction_id' => $search];
}
if ($type !== '') { $where[] = 't.type = :type'; $params['type'] = $type; }
if ($status !== '') { $where[] = 't.status = :status'; $params['status'] = $status; }
if ($from !== '') { $where[] = 't.transaction_date >= :date_from'; $params['date_from'] = $from; }
if ($to !== '') { $where[] = 't.transaction_date <= :date_to'; $params['date_to'] = $to; }
$whereSql = implode(' AND ', $where);
$count = db()->prepare('SELECT COUNT(*) FROM transactions t JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id WHERE ' . $whereSql);
$count->execute($params); $total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage)); $page = min($page, $pages); $offset = ($page - 1) * $perPage;
$sql = 'SELECT t.id, t.type, t.status, t.amount, t.transaction_date, t.description, c.name AS category, u.full_name AS owner_name, u.email AS owner_email FROM transactions t JOIN categories c ON c.id=t.category_id JOIN users u ON u.id=t.user_id WHERE ' . $whereSql . ' ORDER BY ' . $sortColumns[$sort] . ' ' . $direction . ', t.id DESC LIMIT :limit OFFSET :offset';
$stmt = db()->prepare($sql); foreach ($params as $key => $value) $stmt->bindValue(':' . $key, $value); $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute(); $transactions = $stmt->fetchAll();
$flash = pull_flash();
$query = $_GET; unset($query['page']);
?>
<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Администрирование — FinanceAssistant</title><link rel="stylesheet" href="auth.css"></head>
<body><div class="dashboard"><aside class="sidebar"><div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div><nav><a href="dashboard.php">Личный кабинет</a><a href="transactions.php">Мои операции</a><a class="active" href="admin-transactions.php">Записи</a><a href="admin-users.php">Пользователи</a><a href="logout.php">Выйти</a></nav></aside><main class="main">
<div class="topbar"><div><h1>Панель администратора</h1><p><?= e($admin['full_name']) ?> · всего записей: <?= $total ?></p></div><a class="action-link" href="admin-users.php">Управление пользователями →</a></div>
<?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<form class="filters" method="get"><div class="field"><label for="q">Поиск</label><input id="q" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="владелец, категория, описание, ID"></div><div class="field"><label for="type">Тип</label><select id="type" name="type"><option value="">Все</option><option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Доход</option><option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Расход</option></select></div><div class="field"><label for="status">Статус</label><select id="status" name="status"><option value="">Все</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="field"><label for="from">Дата с</label><input id="from" name="from" type="date" value="<?= e($from) ?>"></div><div class="field"><label for="to">Дата по</label><input id="to" name="to" type="date" value="<?= e($to) ?>"></div><div class="filter-actions"><button class="btn" type="submit">Найти</button><a href="admin-transactions.php">Сбросить</a></div></form>
<section class="panel"><h2>Записи · страница <?= $page ?> из <?= $pages ?></h2><div class="table-wrap"><table><thead><tr><th>ID</th><th><a href="?<?= e(http_build_query(array_merge($query, ['sort'=>'date','direction'=>$sort==='date'&&$direction==='DESC'?'asc':'desc']))) ?>">Дата ↕</a></th><th><a href="?<?= e(http_build_query(array_merge($query, ['sort'=>'owner','direction'=>$sort==='owner'&&$direction==='ASC'?'desc':'asc']))) ?>">Владелец ↕</a></th><th>Тип</th><th>Категория</th><th><a href="?<?= e(http_build_query(array_merge($query, ['sort'=>'amount','direction'=>$sort==='amount'&&$direction==='ASC'?'desc':'asc']))) ?>">Сумма ↕</a></th><th>Описание</th><th>Статус</th><th>Действия</th></tr></thead><tbody>
<?php foreach ($transactions as $row): ?><tr><td><?= (int)$row['id'] ?></td><td><?= e(date('d.m.Y', strtotime($row['transaction_date']))) ?></td><td><?= e($row['owner_name']) ?><br><small><?= e($row['owner_email']) ?></small></td><td><?= $row['type'] === 'income' ? 'Доход' : 'Расход' ?></td><td><?= e($row['category']) ?></td><td><?= e(number_format((float)$row['amount'], 2, ',', ' ')) ?> ₽</td><td><?= e($row['description'] ?: '—') ?></td><td><form method="post" action="admin-transaction-action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="return" value="<?= e(http_build_query($_GET)) ?>"><select name="status" onchange="this.form.submit()"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><noscript><button type="submit">Сменить</button></noscript></form></td><td><a href="transaction.php?id=<?= (int)$row['id'] ?>">Просмотр</a> · <a href="admin-transaction-edit.php?id=<?= (int)$row['id'] ?>">Изменить</a><form method="post" action="admin-transaction-action.php" onsubmit="return confirm('Удалить запись #<?= (int)$row['id'] ?>?')" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="return" value="<?= e(http_build_query($_GET)) ?>"><button type="submit">Удалить</button></form></td></tr><?php endforeach; ?>
<?php if (!$transactions): ?><tr><td colspan="9" class="empty">Записей по заданным условиям нет.</td></tr><?php endif; ?></tbody></table></div><div class="pagination"><?php if ($page > 1): ?><a href="?<?= e(http_build_query(array_merge($query, ['page'=>$page-1]))) ?>">← Назад</a><?php endif; ?><span>Страница <?= $page ?> из <?= $pages ?></span><?php if ($page < $pages): ?><a href="?<?= e(http_build_query(array_merge($query, ['page'=>$page+1]))) ?>">Далее →</a><?php endif; ?></div></section></main></div></body></html>
