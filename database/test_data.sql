USE finance_app;

-- Тестовый пароль для всех demo-пользователей: Demo123!
INSERT INTO users (id, full_name, email, password_hash) VALUES
(1, 'Алексей Иванов', 'alexey.demo@example.com', '$2y$12$ZgQQmc3YpGRoqqtjShjCg.r62Hr7HhUU1DELk8EKVDntQNysoX9jS'),
(2, 'Егор Сабанов', 'egor.demo@example.com', '$2y$12$ZgQQmc3YpGRoqqtjShjCg.r62Hr7HhUU1DELk8EKVDntQNysoX9jS');

INSERT INTO categories (id, user_id, name, type, color, icon) VALUES
(1, 1, 'Зарплата', 'income', '#35C77A', 'salary'),
(2, 1, 'Фриланс', 'income', '#4F7CF7', 'freelance'),
(3, 1, 'Продукты', 'expense', '#FFB14A', 'food'),
(4, 1, 'Транспорт', 'expense', '#8F7CFF', 'transport'),
(5, 1, 'Жильё', 'expense', '#FF6B81', 'home'),
(6, 1, 'Развлечения', 'expense', '#D46BFF', 'fun'),
(7, 2, 'Зарплата', 'income', '#35C77A', 'salary'),
(8, 2, 'Фриланс', 'income', '#4F7CF7', 'freelance'),
(9, 2, 'Продукты', 'expense', '#FFB14A', 'food'),
(10, 2, 'Транспорт', 'expense', '#8F7CFF', 'transport'),
(11, 2, 'Жильё', 'expense', '#FF6B81', 'home'),
(12, 2, 'Развлечения', 'expense', '#D46BFF', 'fun'),
(13, 2, 'Здоровье', 'expense', '#48C6EF', 'health');

INSERT INTO import_batches (id, user_id, file_name, file_hash, status, rows_total, rows_imported, rows_skipped, completed_at) VALUES
(1, 2, 'bank_statement_2026_09.csv', SHA2('demo-bank-statement-2026-09', 256), 'processed', 5, 5, 0, CURRENT_TIMESTAMP);

INSERT INTO transactions (id, user_id, category_id, import_batch_id, type, amount, transaction_date, description, external_id, source) VALUES
(1, 1, 1, NULL, 'income', 80000.00, '2026-09-01', 'Заработная плата', 'MAN-0001', 'manual'),
(2, 1, 2, NULL, 'income', 15000.00, '2026-09-10', 'Фриланс-проект', 'MAN-0002', 'manual'),
(3, 1, 3, NULL, 'expense', 12500.00, '2026-09-03', 'Покупки продуктов', 'MAN-0003', 'manual'),
(4, 1, 4, NULL, 'expense', 3500.00, '2026-09-05', 'Транспорт', 'MAN-0004', 'manual'),
(5, 1, 5, NULL, 'expense', 10000.00, '2026-09-06', 'Аренда', 'MAN-0005', 'manual'),
(6, 1, 6, NULL, 'expense', 2500.00, '2026-09-12', 'Кино и досуг', 'MAN-0006', 'manual'),
(7, 2, 7, NULL, 'income', 90000.00, '2026-09-01', 'Заработная плата', 'MAN-1001', 'manual'),
(8, 2, 8, NULL, 'income', 12000.00, '2026-09-15', 'Фриланс', 'MAN-1002', 'manual'),
(9, 2, 9, 1, 'expense', 14790.00, '2026-09-02', 'Банк: супермаркет', 'BANK-001', 'csv'),
(10, 2, 10, 1, 'expense', 3200.00, '2026-09-04', 'Банк: транспорт', 'BANK-002', 'csv'),
(11, 2, 11, 1, 'expense', 18000.00, '2026-09-05', 'Банк: аренда', 'BANK-003', 'csv'),
(12, 2, 12, 1, 'expense', 2400.00, '2026-09-07', 'Банк: развлечения', 'BANK-004', 'csv'),
(13, 2, 13, 1, 'expense', 950.00, '2026-09-09', 'Банк: аптека', 'BANK-005', 'csv');

INSERT INTO budgets (id, user_id, year, month, amount) VALUES
(1, 1, 2026, 9, 50000.00),
(2, 2, 2026, 9, 60000.00);

INSERT INTO budget_limits (id, budget_id, category_id, limit_amount, warning_percent) VALUES
(1, 1, 3, 15000.00, 80),
(2, 1, 4, 5000.00, 80),
(3, 1, 6, 3000.00, 80),
(4, 2, 9, 15000.00, 80),
(5, 2, 10, 5000.00, 80),
(6, 2, 11, 20000.00, 80),
(7, 2, 13, 2000.00, 80);

INSERT INTO notifications (id, user_id, budget_limit_id, type, title, message, is_read) VALUES
(1, 1, 1, 'budget_warning', 'Продукты: близко к лимиту', 'Расходы по категории достигли 83% лимита.', FALSE),
(2, 1, 3, 'budget_exceeded', 'Лимит развлечений превышен', 'Лимит категории превышен на 850 ₽.', FALSE),
(3, 2, 4, 'budget_warning', 'Продукты: близко к лимиту', 'Расходы по категории достигли 98% лимита.', FALSE),
(4, 2, NULL, 'system', 'Импорт завершен', 'Выписка bank_statement_2026_09.csv успешно обработана.', TRUE);
