<?php
declare(strict_types=1);
require __DIR__ . '/../backend/src/bootstrap.php';
$user = require_auth();
$userId = (int)$user['id'];

$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$type = (string)($_GET['type'] ?? '');
$categoryId = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT) ?: null;
$dateFrom = (string)($_GET['from'] ?? '');
$dateTo = (string)($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$validDate = static function (string $date): bool {
    if ($date === '') {
        return true;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
if (!in_array($type, ['', 'income', 'expense'], true)) $type = '';
if (!$validDate($dateFrom)) $dateFrom = '';
if (!$validDate($dateTo)) $dateTo = '';
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

$where = ['t.user_id = :user_id'];
$params = ['user_id' => $userId];
if ($search !== '') {
    $where[] = '(t.description LIKE :search_description OR c.name LIKE :search_category)';
    $params['search_description'] = '%' . $search . '%';
    $params['search_category'] = '%' . $search . '%';
}
if ($type !== '') { $where[] = 't.type = :type'; $params['type'] = $type; }
if ($categoryId !== null) { $where[] = 't.category_id = :category_id'; $params['category_id'] = $categoryId; }
if ($dateFrom !== '') { $where[] = 't.transaction_date >= :date_from'; $params['date_from'] = $dateFrom; }
if ($dateTo !== '') { $where[] = 't.transaction_date <= :date_to'; $params['date_to'] = $dateTo; }
$whereSql = implode(' AND ', $where);
$countStmt = db()->prepare('SELECT COUNT(*) FROM transactions t JOIN categories c ON c.id = t.category_id WHERE ' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare('SELECT t.id, t.type, t.amount, t.transaction_date, t.description, c.name AS category
    FROM transactions t JOIN categories c ON c.id = t.category_id
    WHERE ' . $whereSql . ' ORDER BY t.transaction_date DESC, t.id DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();
$categoryStmt = db()->prepare('SELECT id, name FROM categories WHERE user_id = :user_id OR user_id IS NULL ORDER BY name');
$categoryStmt->execute(['user_id' => $userId]);
$categories = $categoryStmt->fetchAll();
$query = $_GET;
unset($query['page']);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Операции — FinanceAssistant</title>
  <link rel="stylesheet" href="vendor/bootstrap.min.css">
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div>
    <nav>
      <a href="dashboard.php">Личный кабинет</a>
      <a class="active" href="transactions.php">Операции</a>
      <a href="budget.php">Бюджет</a><a href="currency.php">Конвертер валют</a>
      <?php if (($user['role'] ?? 'user') === 'admin'): ?>
        <a href="admin-transactions.php">Администрирование</a>
        <a href="admin-users.php">Пользователи</a>
      <?php endif; ?>
      <a href="logout.php">Выйти</a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1>Мои операции</h1>
        <p>Детали доступны только владельцу записи и администратору.</p>
      </div>
      <a class="action-link" href="dashboard.php">← В кабинет</a>
    </div>

    <form class="filters" method="get">
      <div class="field">
        <label for="q">Поиск по описанию или категории</label>
        <input id="q" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Например, продукты">
      </div>
      <div class="field">
        <label for="type">Тип</label>
        <select id="type" name="type">
          <option value="">Все</option>
          <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Доход</option>
          <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Расход</option>
        </select>
      </div>
      <div class="field">
        <label for="category">Категория</label>
        <select id="category" name="category">
          <option value="">Все категории</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="from">Дата с</label>
        <input id="from" name="from" type="date" value="<?= e($dateFrom) ?>">
      </div>
      <div class="field">
        <label for="to">Дата по</label>
        <input id="to" name="to" type="date" value="<?= e($dateTo) ?>">
      </div>
      <div class="filter-actions">
        <button class="btn" type="submit">Найти</button>
        <a href="transactions.php">Сбросить</a>
      </div>
    </form>
    <section class="panel">
      <h2>Найдено: <?= $total ?> · страница <?= $page ?> из <?= $pages ?></h2>
      <div class="table-wrap">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr><th>Дата</th><th>Тип</th><th>Категория</th><th>Сумма</th><th>Описание</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $transaction): ?>
              <tr>
                <td><?= e(date('d.m.Y', strtotime($transaction['transaction_date']))) ?></td>
                <td><?= $transaction['type'] === 'income' ? 'Доход' : 'Расход' ?></td>
                <td><?= e($transaction['category']) ?></td>
                <td><?= e(number_format((float)$transaction['amount'], 2, ',', ' ')) ?> ₽</td>
                <td><?= e($transaction['description'] ?? '—') ?></td>
                <td><a href="transaction.php?id=<?= (int)$transaction['id'] ?>">Подробнее</a></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
              <tr><td colspan="6" class="empty">Операций по заданным условиям нет.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?= e(http_build_query(array_merge($query, ['page' => $page - 1]))) ?>">← Назад</a>
        <?php endif; ?>
        <span>Страница <?= $page ?> из <?= $pages ?></span>
        <?php if ($page < $pages): ?>
          <a href="?<?= e(http_build_query(array_merge($query, ['page' => $page + 1]))) ?>">Далее →</a>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>
</body>
</html>
