USE finance_app;

-- Быстрая демонстрация куратору: роли, блокировки и статус финансовых записей.
SELECT id, full_name, email, role, IF(is_active, 'активен', 'заблокирован') AS account_state
FROM users
ORDER BY id;

SELECT status, COUNT(*) AS records_count, SUM(amount) AS amount_total
FROM transactions
GROUP BY status
ORDER BY FIELD(status, 'planned', 'completed', 'cancelled');

SELECT t.id, u.email AS owner, c.name AS category, t.type, t.status,
       t.amount, t.transaction_date, t.description
FROM transactions AS t
JOIN users AS u ON u.id = t.user_id
JOIN categories AS c ON c.id = t.category_id
ORDER BY t.transaction_date DESC, t.id DESC
LIMIT 10;
