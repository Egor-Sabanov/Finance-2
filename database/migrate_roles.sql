USE finance_app;

-- Run once on an existing installation. New accounts receive the user role by default.
ALTER TABLE users
    ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER password_hash;

-- Optional demo admin account created by database/test_data.sql.
UPDATE users SET role = 'admin' WHERE email = 'egor.demo@example.com';
