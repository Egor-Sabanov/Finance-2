DROP DATABASE IF EXISTS finance_app;
CREATE DATABASE IF NOT EXISTS finance_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE finance_app;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    color CHAR(7) NULL,
    icon VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT uq_categories_user_name_type UNIQUE (user_id, name, type)
) ENGINE=InnoDB;

CREATE TABLE import_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_hash CHAR(64) NOT NULL,
    status ENUM('pending', 'processed', 'failed') NOT NULL DEFAULT 'pending',
    rows_total INT UNSIGNED NOT NULL DEFAULT 0,
    rows_imported INT UNSIGNED NOT NULL DEFAULT 0,
    rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    CONSTRAINT fk_import_batches_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT uq_import_batches_user_hash UNIQUE (user_id, file_hash)
) ENGINE=InnoDB;

CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    import_batch_id BIGINT UNSIGNED NULL,
    type ENUM('income', 'expense') NOT NULL,
    amount DECIMAL(14,2) UNSIGNED NOT NULL,
    transaction_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    external_id VARCHAR(100) NULL,
    source ENUM('manual', 'csv') NOT NULL DEFAULT 'manual',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_transactions_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_transactions_import_batch
        FOREIGN KEY (import_batch_id) REFERENCES import_batches(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT chk_transactions_amount CHECK (amount > 0),
    INDEX idx_transactions_user_date (user_id, transaction_date),
    INDEX idx_transactions_category_date (category_id, transaction_date),
    INDEX idx_transactions_import_batch (import_batch_id),
    INDEX idx_transactions_external_id (external_id)
) ENGINE=InnoDB;

CREATE TABLE budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_budgets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT uq_budgets_user_period UNIQUE (user_id, year, month),
    CONSTRAINT chk_budgets_month CHECK (month BETWEEN 1 AND 12),
    CONSTRAINT chk_budgets_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE TABLE budget_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    budget_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    limit_amount DECIMAL(14,2) UNSIGNED NOT NULL,
    warning_percent TINYINT UNSIGNED NOT NULL DEFAULT 80,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_limits_budget
        FOREIGN KEY (budget_id) REFERENCES budgets(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_budget_limits_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT uq_budget_limits_budget_category UNIQUE (budget_id, category_id),
    CONSTRAINT chk_budget_limits_amount CHECK (limit_amount > 0),
    CONSTRAINT chk_budget_limits_warning CHECK (warning_percent BETWEEN 1 AND 100)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    budget_limit_id BIGINT UNSIGNED NULL,
    type ENUM('budget_warning', 'budget_exceeded', 'system') NOT NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(500) NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_budget_limit
        FOREIGN KEY (budget_limit_id) REFERENCES budget_limits(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX idx_notifications_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;
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
