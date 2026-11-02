USE finance_app;

-- Run once on a database with the existing role-based schema.
-- Existing accounts stay active and existing transactions become completed.
ALTER TABLE users
    ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE AFTER role;

ALTER TABLE transactions
    ADD COLUMN status ENUM('planned', 'completed', 'cancelled') NOT NULL DEFAULT 'completed' AFTER source,
    ADD INDEX idx_transactions_status_date (status, transaction_date);

-- Demo data for the admin status filter and status selector.
UPDATE transactions SET status='planned' WHERE id IN (2, 5, 8);
UPDATE transactions SET status='cancelled' WHERE id=6;
