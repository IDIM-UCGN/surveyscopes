-- Accounts for the admin area (site admins and editors only; participants
-- never have accounts) and login throttling (keys are HMAC hashes, no plain IPs).

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- binary collation: no accent/width/ß=ss folding, so one row matches exactly
    -- one normalised address (same identity as the throttle keys)
    email VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'editor',
    status VARCHAR(10) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT chk_users_role CHECK (role IN ('admin', 'editor')),
    CONSTRAINT chk_users_status CHECK (status IN ('active', 'disabled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_type VARCHAR(10) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts_lookup (key_type, key_hash, attempted_at),
    KEY idx_login_attempts_age (attempted_at),
    CONSTRAINT chk_login_attempts_type CHECK (key_type IN ('pair', 'account', 'ip', 'setup'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
