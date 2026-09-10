-- ---------------------------------------------------------------------------
-- Buyer accounts - September 2026
--
-- Safe to run against a live database. It only ADDS: one new table and three
-- new settings rows. It does not touch products, pricing, enquiries, admin
-- users or any existing setting, so nothing already in the catalogue can be
-- changed or lost by running it.
--
-- Running it twice is harmless: the table creation is IF NOT EXISTS and the
-- settings use INSERT IGNORE, which leaves an existing value alone rather than
-- resetting a choice that has already been made in the admin panel.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS buyer_accounts (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username       VARCHAR(64)  NULL,
    email          VARCHAR(190) NOT NULL,
    contact_name   VARCHAR(120) NOT NULL,
    company        VARCHAR(160) NULL,
    phone          VARCHAR(60)  NULL,
    country        VARCHAR(120) NULL,
    interest       TEXT         NULL,
    password_hash  VARCHAR(255) NULL,
    status         ENUM('pending','approved','rejected','suspended')
                   NOT NULL DEFAULT 'pending',
    admin_notes    TEXT         NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at  DATETIME     NULL,
    reviewed_at    DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_buyer_email (email),
    UNIQUE KEY uq_buyer_username (username),
    KEY idx_buyer_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 'buyer_gate' decides what signing in actually unlocks and starts at 'none',
-- which hides nothing from the public. That default is deliberate: switching
-- prices on is a commercial decision and it should be made in the admin panel
-- on purpose, not inherited from a deployment.
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('buyer_accounts_enabled', '1'),
    ('buyer_gate',      'none'),
    ('buyer_intro',     'Trade access is for verified buyers. Tell us who you are and we will review your request and send you a login.');
