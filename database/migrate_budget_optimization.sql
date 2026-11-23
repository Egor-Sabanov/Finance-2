USE finance_app;

-- Повторный запуск безопасен: каждый индекс проверяется по текущей схеме.
-- Сначала создаем замену для внешнего ключа transactions.user_id,
-- затем удаляем старый короткий индекс.
SET @schema_name = DATABASE();

SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @schema_name AND table_name = 'transactions'
      AND index_name = 'idx_transactions_user_date_id'
);
SET @ddl = IF(@index_exists = 0,
    'ALTER TABLE transactions ADD INDEX idx_transactions_user_date_id (user_id, transaction_date, id)',
    'SELECT 1');
PREPARE finance_stmt FROM @ddl;
EXECUTE finance_stmt;
DEALLOCATE PREPARE finance_stmt;

SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @schema_name AND table_name = 'transactions'
      AND index_name = 'idx_transactions_user_date'
);
SET @ddl = IF(@index_exists > 0,
    'ALTER TABLE transactions DROP INDEX idx_transactions_user_date',
    'SELECT 1');
PREPARE finance_stmt FROM @ddl;
EXECUTE finance_stmt;
DEALLOCATE PREPARE finance_stmt;

SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @schema_name AND table_name = 'transactions'
      AND index_name = 'idx_transactions_user_period_summary'
);
SET @ddl = IF(@index_exists = 0,
    'ALTER TABLE transactions ADD INDEX idx_transactions_user_period_summary (user_id, status, type, transaction_date, amount)',
    'SELECT 1');
PREPARE finance_stmt FROM @ddl;
EXECUTE finance_stmt;
DEALLOCATE PREPARE finance_stmt;

SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @schema_name AND table_name = 'transactions'
      AND index_name = 'idx_transactions_user_category_period'
);
SET @ddl = IF(@index_exists = 0,
    'ALTER TABLE transactions ADD INDEX idx_transactions_user_category_period (user_id, category_id, status, type, transaction_date, amount)',
    'SELECT 1');
PREPARE finance_stmt FROM @ddl;
EXECUTE finance_stmt;
DEALLOCATE PREPARE finance_stmt;
