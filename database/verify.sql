USE finance_app;

SHOW TABLES;

SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'categories', COUNT(*) FROM categories
UNION ALL SELECT 'transactions', COUNT(*) FROM transactions
UNION ALL SELECT 'import_batches', COUNT(*) FROM import_batches
UNION ALL SELECT 'budgets', COUNT(*) FROM budgets
UNION ALL SELECT 'budget_limits', COUNT(*) FROM budget_limits
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications;
SELECT currency_code, rate_rub, nominal, rate_date, fetched_at
FROM exchange_rates
ORDER BY currency_code;

SELECT
    t.id,
    u.email,
    c.name AS category,
    t.type,
    t.status,
    t.amount,
    t.transaction_date,
    t.source
FROM transactions t
JOIN users u ON u.id = t.user_id
JOIN categories c ON c.id = t.category_id
ORDER BY t.id;

SELECT role, is_active, COUNT(*) AS user_count
FROM users
GROUP BY role, is_active
ORDER BY role, is_active;

SELECT status, COUNT(*) AS transaction_count, SUM(amount) AS amount_total
FROM transactions
GROUP BY status
ORDER BY status;

SELECT
    b.year,
    b.month,
    b.amount AS total_budget,
    SUM(bl.limit_amount) AS category_limits
FROM budgets b
LEFT JOIN budget_limits bl ON bl.budget_id = b.id
GROUP BY b.id, b.year, b.month, b.amount;

-- Составные индексы для бюджета, лимитов и постраничного списка.
SHOW INDEX FROM transactions WHERE Key_name LIKE 'idx_transactions_user%';

-- Проверка сводного запроса по месяцу.
EXPLAIN
SELECT COALESCE(SUM(amount), 0) AS spent
FROM transactions
WHERE user_id = 2
  AND status = 'completed'
  AND type = 'expense'
  AND transaction_date >= '2026-09-01'
  AND transaction_date < '2026-10-01';

-- Проверка расчета лимита по категории.
EXPLAIN
SELECT COALESCE(SUM(amount), 0) AS spent
FROM transactions
WHERE user_id = 2
  AND category_id = 9
  AND status = 'completed'
  AND type = 'expense'
  AND transaction_date >= '2026-09-01'
  AND transaction_date < '2026-10-01';
