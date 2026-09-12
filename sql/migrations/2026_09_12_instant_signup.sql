-- ---------------------------------------------------------------------------
-- Instant sign-up, and prices as a separate grant - September 2026
--
-- Two changes, and the second exists because of the first.
--
-- 1. 'buyer_signup_mode' lets the owner choose between vetting every request by
--    hand (how this started) and letting visitors create their own account and
--    get in straight away.
--
-- 2. Once anybody can create an account, "signed in" can no longer mean "may
--    see our prices" - a competitor would simply sign up. So price visibility
--    moves onto its own column, off by default, which the owner turns on per
--    account. Approving a request in the admin panel sets it; self-registering
--    never does.
--
-- Existing accounts were all issued by hand from the admin panel, so they are
-- back-filled to 1 - that is what they already meant.
--
-- Safe to run twice: the settings use INSERT IGNORE, and the column add is
-- guarded so a second run is a no-op rather than an error.
-- ---------------------------------------------------------------------------

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'buyer_accounts'
                AND COLUMN_NAME = 'pricing_access');
SET @sql := IF(@col = 0,
    'ALTER TABLE buyer_accounts ADD COLUMN pricing_access TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE buyer_accounts SET pricing_access = 1
 WHERE status = 'approved' AND password_hash IS NOT NULL AND reviewed_at IS NOT NULL;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('buyer_signup_mode', 'vetted');
