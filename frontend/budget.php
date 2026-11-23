<?php
declare(strict_types=1);

require __DIR__ . '/../backend/src/bootstrap.php';
$user = require_auth();
$userId = (int)$user['id'];

$requestedYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$requestedMonth = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
$latestBudget = null;
if ($requestedYear === null && $requestedMonth === null) {
    $latestStmt = db()->prepare('SELECT year, month FROM budgets WHERE user_id = :user_id ORDER BY year DESC, month DESC LIMIT 1');
    $latestStmt->execute(['user_id' => $userId]);
    $latestBudget = $latestStmt->fetch() ?: null;
}
$year = $requestedYear ?: (int)($latestBudget['year'] ?? date('Y'));
$month = $requestedMonth ?: (int)($latestBudget['month'] ?? date('n'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

$periodStart = sprintf('%04d-%02d-01', $year, $month);
$periodEnd = (new DateTimeImmutable($periodStart))->modify('+1 month')->format('Y-m-d');
$monthNames = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
    5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
    9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedYear = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT) ?: $year;
    $postedMonth = filter_input(INPUT_POST, 'month', FILTER_VALIDATE_INT) ?: $month;
    $returnTo = 'budget.php?year=' . $postedYear . '&month=' . $postedMonth;

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Форма устарела. Обновите страницу и попробуйте снова.');
    } elseif ($postedYear < 2000 || $postedYear > 2100 || $postedMonth < 1 || $postedMonth > 12) {
        set_flash('error', 'Выбран неверный месяц.');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $amountInput = str_replace(',', '.', trim((string)($_POST['amount'] ?? '')));
        $amountIsValid = preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $amountInput) === 1 && (float)$amountInput > 0;
        $postPeriodStart = sprintf('%04d-%02d-01', $postedYear, $postedMonth);
        $postPeriodEnd = (new DateTimeImmutable($postPeriodStart))->modify('+1 month')->format('Y-m-d');

        try {
            if ($action === 'save_budget') {
                if (!$amountIsValid) {
                    throw new DomainException('Введите сумму бюджета больше нуля, не более 12 цифр до запятой и двух после.');
                }

                $limitsTotalStmt = db()->prepare(
                    'SELECT COALESCE(SUM(bl.limit_amount), 0)
                     FROM budget_limits AS bl
                     JOIN budgets AS b ON b.id = bl.budget_id
                     WHERE b.user_id = :user_id AND b.year = :year AND b.month = :month'
                );
                $limitsTotalStmt->execute(['user_id' => $userId, 'year' => $postedYear, 'month' => $postedMonth]);
                if ((float)$limitsTotalStmt->fetchColumn() > (float)$amountInput) {
                    throw new DomainException('Общий бюджет не может быть меньше суммы уже заданных лимитов.');
                }

                $stmt = db()->prepare(
                    'INSERT INTO budgets (user_id, year, month, amount)
                     VALUES (:user_id, :year, :month, :amount)
                     ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'year' => $postedYear,
                    'month' => $postedMonth,
                    'amount' => $amountInput,
                ]);
                set_flash('success', 'Общий бюджет на месяц сохранен.');
            } elseif ($action === 'save_limit') {
                $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: 0;
                $warningPercent = filter_input(INPUT_POST, 'warning_percent', FILTER_VALIDATE_INT) ?: 0;
                if (!$amountIsValid || $categoryId < 1 || $warningPercent < 1 || $warningPercent > 100) {
                    throw new DomainException('Проверьте сумму лимита, категорию и порог предупреждения.');
                }

                $budgetStmt = db()->prepare('SELECT id, amount FROM budgets WHERE user_id = :user_id AND year = :year AND month = :month LIMIT 1');
                $budgetStmt->execute(['user_id' => $userId, 'year' => $postedYear, 'month' => $postedMonth]);
                $budgetId = (int)$budgetStmt->fetchColumn();
                if ($budgetId < 1) {
                    throw new DomainException('Сначала сохраните общий бюджет на выбранный месяц.');
                }

                $categoryStmt = db()->prepare(
                    "SELECT id, name FROM categories
                     WHERE id = :category_id
                       AND (user_id = :user_id OR user_id IS NULL)
                       AND type = 'expense'
                     LIMIT 1"
                );
                $categoryStmt->execute(['category_id' => $categoryId, 'user_id' => $userId]);
                $category = $categoryStmt->fetch();
                if (!$category) {
                    throw new DomainException('Выберите доступную категорию расходов.');
                }

                $limitsTotalStmt = db()->prepare(
                    'SELECT COALESCE(SUM(limit_amount), 0)
                     FROM budget_limits
                     WHERE budget_id = :budget_id AND category_id <> :category_id'
                );
                $limitsTotalStmt->execute(['budget_id' => $budgetId, 'category_id' => $categoryId]);
                if ((float)$limitsTotalStmt->fetchColumn() + (float)$amountInput > (float)$budget['amount']) {
                    throw new DomainException('Сумма категорийных лимитов не может превышать общий бюджет.');
                }

                db()->beginTransaction();
                $limitStmt = db()->prepare(
                    'INSERT INTO budget_limits (budget_id, category_id, limit_amount, warning_percent)
                     VALUES (:budget_id, :category_id, :limit_amount, :warning_percent)
                     ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount), warning_percent = VALUES(warning_percent)'
                );
                $limitStmt->execute([
                    'budget_id' => $budgetId,
                    'category_id' => $categoryId,
                    'limit_amount' => $amountInput,
                    'warning_percent' => $warningPercent,
                ]);

                $limitIdStmt = db()->prepare('SELECT id FROM budget_limits WHERE budget_id = :budget_id AND category_id = :category_id');
                $limitIdStmt->execute(['budget_id' => $budgetId, 'category_id' => $categoryId]);
                $limitId = (int)$limitIdStmt->fetchColumn();

                $spentStmt = db()->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM transactions
                     WHERE user_id = :user_id AND category_id = :category_id
                       AND status = 'completed' AND type = 'expense'
                       AND transaction_date >= :period_start AND transaction_date < :period_end"
                );
                $spentStmt->execute([
                    'user_id' => $userId,
                    'category_id' => $categoryId,
                    'period_start' => $postPeriodStart,
                    'period_end' => $postPeriodEnd,
                ]);
                $spent = (float)$spentStmt->fetchColumn();
                $limitAmount = (float)$amountInput;
                $percent = $limitAmount > 0 ? (int)floor(($spent / $limitAmount) * 100) : 0;

                if ($percent >= $warningPercent) {
                    $noticeType = $spent > $limitAmount ? 'budget_exceeded' : 'budget_warning';
                    $noticeTitle = $spent > $limitAmount
                        ? 'Лимит превышен: ' . $category['name']
                        : 'Лимит скоро закончится: ' . $category['name'];
                    $noticeMessage = sprintf(
                        'Расходы за месяц составляют %d%% лимита (%s из %s ₽).',
                        $percent,
                        number_format($spent, 2, ',', ' '),
                        number_format($limitAmount, 2, ',', ' ')
                    );

                    $noticeExists = db()->prepare('SELECT id FROM notifications WHERE budget_limit_id = :limit_id AND type = :type LIMIT 1');
                    $noticeExists->execute(['limit_id' => $limitId, 'type' => $noticeType]);
                    if (!$noticeExists->fetchColumn()) {
                        $noticeInsert = db()->prepare(
                            'INSERT INTO notifications (user_id, budget_limit_id, type, title, message)
                             VALUES (:user_id, :limit_id, :type, :title, :message)'
                        );
                        $noticeInsert->execute([
                            'user_id' => $userId,
                            'limit_id' => $limitId,
                            'type' => $noticeType,
                            'title' => $noticeTitle,
                            'message' => $noticeMessage,
                        ]);
                    }
                }

                db()->commit();
                set_flash('success', 'Лимит по категории сохранен.');
            } elseif ($action === 'mark_read') {
                $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT) ?: 0;
                if ($notificationId < 1) {
                    throw new DomainException('Уведомление не найдено.');
                }
                $readStmt = db()->prepare('UPDATE notifications SET is_read = TRUE, read_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id');
                $readStmt->execute(['id' => $notificationId, 'user_id' => $userId]);
                set_flash('success', 'Уведомление отмечено прочитанным.');
            } else {
                throw new DomainException('Неизвестное действие.');
            }
        } catch (DomainException $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            set_flash('error', $error->getMessage());
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            app_log_error($error);
            set_flash('error', 'Не удалось сохранить бюджет. Проверьте данные и попробуйте еще раз.');
        }

        header('Location: ' . $returnTo);
        exit;
    }
}

$budgetStmt = db()->prepare('SELECT id, amount FROM budgets WHERE user_id = :user_id AND year = :year AND month = :month LIMIT 1');
$budgetStmt->execute(['user_id' => $userId, 'year' => $year, 'month' => $month]);
$budget = $budgetStmt->fetch() ?: null;
$budgetId = $budget ? (int)$budget['id'] : 0;

$spentStmt = db()->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM transactions
     WHERE user_id = :user_id AND status = 'completed' AND type = 'expense'
       AND transaction_date >= :period_start AND transaction_date < :period_end"
);
$spentStmt->execute(['user_id' => $userId, 'period_start' => $periodStart, 'period_end' => $periodEnd]);
$totalSpent = (float)$spentStmt->fetchColumn();
$totalBudget = (float)($budget['amount'] ?? 0);
$totalPercent = $totalBudget > 0 ? (int)floor(($totalSpent / $totalBudget) * 100) : 0;
$totalProgress = min(100, $totalPercent);

$limits = [];
if ($budgetId > 0) {
    $limitListStmt = db()->prepare(
        "SELECT bl.id, bl.category_id, c.name AS category, c.color, bl.limit_amount,
                bl.warning_percent, COALESCE(SUM(t.amount), 0) AS spent
         FROM budget_limits AS bl
         JOIN budgets AS b ON b.id = bl.budget_id AND b.user_id = :user_id
         JOIN categories AS c ON c.id = bl.category_id
         LEFT JOIN transactions AS t
           ON t.user_id = b.user_id
          AND t.category_id = bl.category_id
          AND t.status = 'completed'
          AND t.type = 'expense'
          AND t.transaction_date >= :period_start
          AND t.transaction_date < :period_end
         WHERE bl.budget_id = :budget_id
         GROUP BY bl.id, bl.category_id, c.name, c.color, bl.limit_amount, bl.warning_percent
         ORDER BY c.name"
    );
    $limitListStmt->execute([
        'user_id' => $userId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'budget_id' => $budgetId,
    ]);
    $limits = $limitListStmt->fetchAll();
}

$categoryStmt = db()->prepare("SELECT id, name FROM categories WHERE (user_id = :user_id OR user_id IS NULL) AND type = 'expense' ORDER BY name");
$categoryStmt->execute(['user_id' => $userId]);
$categories = $categoryStmt->fetchAll();

$notificationStmt = db()->prepare(
    'SELECT id, type, title, message, created_at
     FROM notifications
     WHERE user_id = :user_id AND is_read = FALSE
     ORDER BY created_at DESC, id DESC
     LIMIT 5'
);
$notificationStmt->execute(['user_id' => $userId]);
$notifications = $notificationStmt->fetchAll();
$flash = pull_flash();

function money(float $value): string
{
    return number_format($value, 2, ',', ' ') . ' ₽';
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Бюджет — FinanceAssistant</title>
  <link rel="stylesheet" href="vendor/bootstrap.min.css">
  <link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="dashboard">
  <aside class="sidebar">
    <div class="logo"><div class="logo-mark">₽</div>FinanceAssistant</div>
    <nav>
      <a href="dashboard.php">Главная</a>
      <a href="transactions.php">Операции</a>
      <a class="active" href="budget.php">Бюджет</a>
      <a href="currency.php">Конвертер валют</a>
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
        <h1>Бюджет и лимиты</h1>
        <p>Планируйте расходы и следите за выполнением бюджета по категориям.</p>
      </div>
      <a class="action-link" href="dashboard.php">← В кабинет</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <form class="filters budget-period" method="get">
      <div class="field">
        <label for="month">Месяц</label>
        <select id="month" name="month">
          <?php foreach ($monthNames as $monthNumber => $monthLabel): ?>
            <option value="<?= $monthNumber ?>" <?= $month === $monthNumber ? 'selected' : '' ?>><?= e($monthLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="year">Год</label>
        <input id="year" name="year" type="number" min="2000" max="2100" value="<?= $year ?>">
      </div>
      <div class="filter-actions"><button class="btn" type="submit">Показать</button></div>
    </form>

    <section class="stats budget-stats">
      <article class="stat">
        <div class="label">Общий бюджет</div>
        <div class="value"><?= $totalBudget > 0 ? e(money($totalBudget)) : 'Не задан' ?></div>
      </article>
      <article class="stat">
        <div class="label">Завершенные расходы</div>
        <div class="value"><?= e(money($totalSpent)) ?></div>
      </article>
      <article class="stat">
        <div class="label">Использовано</div>
        <div class="value <?= $totalPercent > 100 ? 'budget-danger' : 'ok' ?>">
          <?= $totalBudget > 0 ? $totalPercent . '%' : '—' ?>
        </div>
      </article>
    </section>

    <?php if ($totalBudget > 0): ?>
      <section class="panel budget-overview">
        <div class="budget-progress-heading">
          <h2>Бюджет за <?= e($monthNames[$month] . ' ' . $year) ?></h2>
          <span><?= e(money($totalSpent)) ?> из <?= e(money($totalBudget)) ?></span>
        </div>
        <div class="progress-track" role="progressbar" aria-label="Использование бюджета" aria-valuenow="<?= $totalProgress ?>" aria-valuemin="0" aria-valuemax="100">
          <span class="progress-fill <?= $totalPercent > 100 ? 'is-danger' : '' ?>" style="width: <?= $totalProgress ?>%"></span>
        </div>
        <p class="budget-hint">
          <?= $totalPercent > 100
              ? 'Месячный бюджет превышен на ' . e(money($totalSpent - $totalBudget))
              : 'Осталось ' . e(money(max(0, $totalBudget - $totalSpent))) ?>
        </p>
      </section>
    <?php endif; ?>

    <section class="panel">
      <h2>Общий план на месяц</h2>
      <form class="budget-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_budget">
        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="month" value="<?= $month ?>">
        <div class="field">
          <label for="budget_amount">Сумма бюджета, ₽</label>
          <input id="budget_amount" name="amount" type="number" min="0.01" step="0.01" max="999999999999.99" value="<?= $totalBudget > 0 ? e(number_format($totalBudget, 2, '.', '')) : '' ?>" placeholder="Например, 60000" required>
        </div>
        <button class="btn" type="submit">Сохранить бюджет</button>
      </form>
    </section>

    <section class="panel">
      <div class="budget-progress-heading"><h2>Лимиты по категориям</h2><span><?= count($limits) ?> категорий</span></div>
      <?php if ($totalBudget > 0 && $categories): ?>
        <form class="budget-form limit-form" method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_limit">
          <input type="hidden" name="year" value="<?= $year ?>">
          <input type="hidden" name="month" value="<?= $month ?>">
          <div class="field">
            <label for="category_id">Категория расходов</label>
            <select id="category_id" name="category_id" required>
              <option value="">Выберите категорию</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="limit_amount">Лимит, ₽</label>
            <input id="limit_amount" name="amount" type="number" min="0.01" step="0.01" max="999999999999.99" placeholder="Например, 12000" required>
          </div>
          <div class="field">
            <label for="warning_percent">Предупредить при, %</label>
            <input id="warning_percent" name="warning_percent" type="number" min="1" max="100" value="80" required>
          </div>
          <button class="btn" type="submit">Сохранить лимит</button>
        </form>
      <?php elseif ($totalBudget <= 0): ?>
        <p class="budget-hint">Сначала задайте общий месячный бюджет, затем добавьте лимиты.</p>
      <?php else: ?>
        <p class="budget-hint">Для бюджета пока нет доступных категорий расходов.</p>
      <?php endif; ?>

      <?php if ($limits): ?>
        <div class="budget-limit-list">
          <?php foreach ($limits as $limit): ?>
            <?php
              $limitAmount = (float)$limit['limit_amount'];
              $limitSpent = (float)$limit['spent'];
              $limitPercent = $limitAmount > 0 ? (int)floor(($limitSpent / $limitAmount) * 100) : 0;
              $isExceeded = $limitSpent > $limitAmount;
              $isWarning = !$isExceeded && $limitPercent >= (int)$limit['warning_percent'];
              $progress = min(100, $limitPercent);
            ?>
            <article class="budget-limit-card">
              <div class="budget-progress-heading">
                <h3><?= e($limit['category']) ?></h3>
                <span><?= e(money($limitSpent)) ?> / <?= e(money($limitAmount)) ?></span>
              </div>
              <div class="progress-track" role="progressbar" aria-label="<?= e('Лимит: ' . $limit['category']) ?>" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="progress-fill <?= $isExceeded ? 'is-danger' : ($isWarning ? 'is-warning' : '') ?>" style="width: <?= $progress ?>%"></span>
              </div>
              <div class="budget-limit-meta">
                <span>Порог уведомления: <?= (int)$limit['warning_percent'] ?>%</span>
                <strong class="<?= $isExceeded ? 'budget-danger' : ($isWarning ? 'budget-warning' : 'ok') ?>">
                  <?= $isExceeded
                      ? 'Превышен на ' . e(money($limitSpent - $limitAmount))
                      : ($isWarning ? 'Пора проверить расходы' : 'В пределах лимита') ?>
                </strong>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php elseif ($totalBudget > 0): ?>
        <p class="budget-hint">Лимиты еще не добавлены. Выберите категорию и задайте месячное ограничение.</p>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="budget-progress-heading"><h2>Уведомления</h2><span>Непрочитанных: <?= count($notifications) ?></span></div>
      <?php if ($notifications): ?>
        <div class="notification-list">
          <?php foreach ($notifications as $notification): ?>
            <article class="notification-item <?= $notification['type'] === 'budget_exceeded' ? 'is-danger' : '' ?>">
              <div>
                <strong><?= e($notification['title']) ?></strong>
                <p><?= e($notification['message']) ?></p>
                <small><?= e(date('d.m.Y H:i', strtotime($notification['created_at']))) ?></small>
              </div>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                <button class="quiet-button" type="submit">Прочитано</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="budget-hint">Непрочитанных уведомлений нет.</p>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
