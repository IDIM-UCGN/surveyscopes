-- People and their login credentials, plus login throttling.
-- members: the person record (e-mail, optional name, admin flag, permission
--   to create surveys, status). It can exist before the person has an
--   account; claimed_at is set when they get one.
-- users: credentials only, one row per member who has claimed an account.
-- login_attempts: throttling; keys are HMAC hashes (no plain e-mail, no IP).

CREATE TABLE IF NOT EXISTS members (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- binary collation: no accent/width/ß=ss folding, so one row matches exactly
    -- one normalised ASCII address (same identity as the throttle keys)
    email VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    -- full name, optional (shown to other members)
    display_name VARCHAR(255) NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    -- permission to create surveys. Admins have it implicitly: the application
    -- checks is_admin = 1 OR create_surveys_allowed = 1, not this column alone
    create_surveys_allowed TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    claimed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_members_email (email),
    KEY idx_members_created_by (created_by),
    CONSTRAINT chk_members_is_admin CHECK (is_admin IN (0, 1)),
    CONSTRAINT chk_members_create_surveys_allowed CHECK (create_surveys_allowed IN (0, 1)),
    CONSTRAINT chk_members_status CHECK (status IN ('active', 'disabled')),
    CONSTRAINT fk_members_created_by FOREIGN KEY (created_by)
        REFERENCES members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    member_id INT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_member (member_id),
    CONSTRAINT fk_users_member FOREIGN KEY (member_id)
        REFERENCES members (id) ON DELETE RESTRICT
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
