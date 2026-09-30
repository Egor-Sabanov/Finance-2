CREATE DATABASE IF NOT EXISTS finance_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE finance_app;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
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
