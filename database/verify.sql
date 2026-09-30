USE finance_app;

SHOW TABLES;

SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
UNION ALL SELECT 'categories', COUNT(*) FROM categories
UNION ALL SELECT 'transactions', COUNT(*) FROM transactions
UNION ALL SELECT 'import_batches', COUNT(*) FROM import_batches
UNION ALL SELECT 'budgets', COUNT(*) FROM budgets
UNION ALL SELECT 'budget_limits', COUNT(*) FROM budget_limits
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications;

SELECT
    t.id,
    u.email,
    c.name AS category,
    t.type,
    t.amount,
    t.transaction_date,
    t.source
FROM transactions t
JOIN users u ON u.id = t.user_id
JOIN categories c ON c.id = t.category_id
ORDER BY t.id;

SELECT
    b.year,
    b.month,
    b.amount AS total_budget,
    SUM(bl.limit_amount) AS category_limits
FROM budgets b
LEFT JOIN budget_limits bl ON bl.budget_id = b.id
GROUP BY b.id, b.year, b.month, b.amount;
