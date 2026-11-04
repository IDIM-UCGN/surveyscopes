-- Instance settings and admin-managed pages (front page, imprint, privacy).
-- source: 'seed' = provided by the instance seed (may be updated by a newer seed),
--         'admin' = edited by the site admin (never overwritten by a seed).

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    value MEDIUMTEXT NOT NULL,
    source VARCHAR(10) NOT NULL DEFAULT 'admin',
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    page_key VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_translations (
    page_key VARCHAR(50) NOT NULL,
    locale CHAR(2) NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(300) NOT NULL DEFAULT '',
    source VARCHAR(10) NOT NULL DEFAULT 'admin',
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (page_key, locale),
    CONSTRAINT fk_page_translations_page FOREIGN KEY (page_key)
        REFERENCES pages (page_key) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
