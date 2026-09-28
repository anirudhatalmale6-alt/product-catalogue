-- ---------------------------------------------------------------------------
-- Shopify link - September 2026
--
-- Shopify owns the consumer side. The catalogue owns B2B. This migration adds
-- the two pieces needed to keep them in step WITHOUT either one becoming the
-- other's master:
--
-- 1. shopify_products - a local copy of what Shopify told us last time we
--    asked. Purely a cache: it is safe to empty and refill, nothing here is
--    authoritative and nothing is ever written back to Shopify.
--
-- 2. products.shopify_product_id - the link. Deliberately Shopify's own
--    numeric id rather than a SKU, because every one of the 197 catalogue
--    products has a NULL sku, and matching on names breaks silently the first
--    time either side renames something. An id survives a rename on both ends.
--
-- Safe to run twice and safe on a live database: it only adds.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS shopify_products (
    shopify_product_id BIGINT UNSIGNED NOT NULL,
    shopify_variant_id BIGINT UNSIGNED NULL,
    title              VARCHAR(255) NOT NULL,
    handle             VARCHAR(255) NULL,
    sku                VARCHAR(120) NULL,
    price              DECIMAL(12,2) NULL,
    currency           VARCHAR(8)   NULL,
    inventory_qty      INT          NULL,
    -- Shopify's own answer to "can this be bought right now", which is not the
    -- same question as "is inventory_qty above zero" - a product can be set to
    -- continue selling when out of stock.
    is_available       TINYINT(1)   NOT NULL DEFAULT 0,
    online_url         VARCHAR(500) NULL,
    image_url          VARCHAR(500) NULL,
    status             VARCHAR(32)  NULL,
    synced_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (shopify_product_id),
    KEY idx_shopify_title (title),
    KEY idx_shopify_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'products'
                AND COLUMN_NAME = 'shopify_product_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE products ADD COLUMN shopify_product_id BIGINT UNSIGNED NULL AFTER sku',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UNIQUE so one Shopify product cannot end up linked to two catalogue rows.
-- MySQL allows many NULLs in a unique index, so the 197 unlinked products are
-- fine. Without this a mis-click in the linking screen would quietly produce
-- two catalogue pages both claiming to sell the same Shopify item.
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'products'
                AND INDEX_NAME = 'uq_products_shopify');
SET @sql := IF(@idx = 0,
    'ALTER TABLE products ADD UNIQUE KEY uq_products_shopify (shopify_product_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('shopify_enabled',     '0'),
    ('shopify_buy_label',   'Buy online'),
    ('shopify_show_price',  '1');
