USE finance_app;

-- Показывает, какие индексы доступны для финансовых сводок.
SHOW INDEX FROM transactions;

-- Ежемесячная сумма завершенных расходов пользователя.
-- Для фильтрации периода и покрытия суммы предназначен
-- idx_transactions_user_period_summary.
EXPLAIN
SELECT COALESCE(SUM(amount), 0) AS spent
FROM transactions
WHERE user_id = 2
  AND status = 'completed'
  AND type = 'expense'
  AND transaction_date >= '2026-09-01'
  AND transaction_date < '2026-10-01';

-- Расходы по категориям для расчета выполнения лимитов.
-- Для равенств пользователя, категории, статуса и типа плюс периода
-- предназначен idx_transactions_user_category_period.
EXPLAIN
SELECT category_id, COALESCE(SUM(amount), 0) AS spent
FROM transactions
WHERE user_id = 2
  AND category_id = 9
  AND status = 'completed'
  AND type = 'expense'
  AND transaction_date >= '2026-09-01'
  AND transaction_date < '2026-10-01'
GROUP BY category_id;

-- Список операций с постраничной выдачей пользователя.
-- Для фильтра пользователя и порядка по дате/ID предназначен
-- idx_transactions_user_date_id.
EXPLAIN
SELECT id, type, amount, transaction_date, description
FROM transactions
WHERE user_id = 2
ORDER BY transaction_date DESC, id DESC
LIMIT 20 OFFSET 0;
