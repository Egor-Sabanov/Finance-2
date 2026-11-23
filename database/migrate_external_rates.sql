USE finance_app;

CREATE TABLE IF NOT EXISTS exchange_rates (
    currency_code CHAR(3) PRIMARY KEY,
    rate_rub DECIMAL(18,8) UNSIGNED NOT NULL,
    nominal DECIMAL(14,4) UNSIGNED NOT NULL DEFAULT 1,
    rate_date DATE NOT NULL,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_exchange_rates_positive CHECK (rate_rub > 0 AND nominal > 0),
    INDEX idx_exchange_rates_fetched_at (fetched_at)
) ENGINE=InnoDB;
