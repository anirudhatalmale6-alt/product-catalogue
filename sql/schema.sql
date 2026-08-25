-- ---------------------------------------------------------------------------
-- Database-Driven Product Catalogue - schema
-- MySQL 5.7+ / 8.x / MariaDB 10.3+
-- ---------------------------------------------------------------------------
-- Import with:  mysql -u USER -p DATABASE < sql/schema.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS product_specs;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS origins;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Categories (product types). Self-referencing so you can nest if you ever
-- want sub-types; parent_id NULL = top level.
--
-- spec_template holds the specification row headings that a product in this
-- category normally carries, one per line, as "Group|Name" or just "Name".
-- The product form offers them as blank rows so the same headings get used
-- across the whole category instead of being retyped slightly differently
-- every time. It is only ever a suggestion - nothing is written to a product
-- until you type a value.
-- ---------------------------------------------------------------------------
CREATE TABLE categories (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id     INT UNSIGNED NULL,
    name          VARCHAR(120)  NOT NULL,
    slug          VARCHAR(140)  NOT NULL,
    description   TEXT          NULL,
    spec_template TEXT          NULL,
    image_path    VARCHAR(255)  NULL,
    sort_order    INT           NOT NULL DEFAULT 0,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_parent (parent_id),
    KEY idx_categories_active_sort (is_active, sort_order),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id)
        REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Origins. Country of origin cuts across every product type - a buyer wants
-- "Thai frozen items", not a separate Thailand category duplicated under each
-- type - so it is its own dimension rather than a second category tree.
-- Deleting an origin leaves its products in place with no origin set.
-- ---------------------------------------------------------------------------
CREATE TABLE origins (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120) NOT NULL,
    slug       VARCHAR(140) NOT NULL,
    code       VARCHAR(8)   NULL,          -- ISO-3166 alpha-2, for flags/exports
    sort_order INT          NOT NULL DEFAULT 0,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_origins_slug (slug),
    KEY idx_origins_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Products
-- stock_status is the human-facing availability shown on the front-end.
-- stock_qty is optional bookkeeping; leave NULL if you do not track counts.
--
-- price is NULLABLE on purpose. For export sourcing a figure usually depends
-- on volume and incoterm, so an item with no price is not an incomplete
-- record - it is a "price on request" line. The listing renders it that way
-- and the price filter skips those rows rather than treating them as zero.
-- ---------------------------------------------------------------------------
CREATE TABLE products (
    id                INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    category_id       INT UNSIGNED   NULL,
    origin_id         INT UNSIGNED   NULL,
    sku               VARCHAR(64)    NULL,
    name              VARCHAR(200)   NOT NULL,
    slug              VARCHAR(220)   NOT NULL,
    short_description VARCHAR(300)   NULL,
    description       TEXT           NULL,
    price             DECIMAL(12,2)  NULL DEFAULT NULL,
    sale_price        DECIMAL(12,2)  NULL,
    stock_status      ENUM('in_stock','low_stock','out_of_stock','preorder',
                           'made_to_order','discontinued')
                                     NOT NULL DEFAULT 'made_to_order',
    stock_qty         INT            NULL,
    brand             VARCHAR(120)   NULL,
    weight_grams      INT UNSIGNED   NULL,
    is_active         TINYINT(1)     NOT NULL DEFAULT 1,
    is_featured       TINYINT(1)     NOT NULL DEFAULT 0,
    sort_order        INT            NOT NULL DEFAULT 0,
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_origin (origin_id),
    KEY idx_products_active (is_active),
    KEY idx_products_status (stock_status),
    KEY idx_products_price (price),
    KEY idx_products_name (name),
    KEY idx_products_brand (brand),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_products_origin FOREIGN KEY (origin_id)
        REFERENCES origins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Product images - many per product, one flagged primary.
-- file_path is relative to public/uploads/  (e.g. "products/abc123.jpg")
-- ---------------------------------------------------------------------------
CREATE TABLE product_images (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    alt_text    VARCHAR(200) NULL,
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_images_product (product_id, sort_order),
    KEY idx_images_primary (product_id, is_primary),
    CONSTRAINT fk_images_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Technical specifications - name/value pairs so any product can carry any
-- spec without a schema change. spec_group lets you print them in sections
-- ("Dimensions", "Electrical", ...). Leave spec_group NULL for a flat table.
-- ---------------------------------------------------------------------------
CREATE TABLE product_specs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    spec_group  VARCHAR(80)  NULL,
    spec_name   VARCHAR(120) NOT NULL,
    spec_value  VARCHAR(400) NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_specs_product (product_id, sort_order),
    CONSTRAINT fk_specs_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Admin users. Passwords are stored as PHP password_hash() bcrypt hashes -
-- never plain text. Create the first one with tools/create_admin.php
-- ---------------------------------------------------------------------------
CREATE TABLE admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64)  NOT NULL,
    full_name     VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Failed-login throttling. Rows older than an hour can be pruned freely.
-- ---------------------------------------------------------------------------
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_address   VARBINARY(16) NOT NULL,
    username     VARCHAR(64)   NULL,
    was_success  TINYINT(1)    NOT NULL DEFAULT 0,
    attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Simple key/value settings (site name, currency, items per page...)
-- ---------------------------------------------------------------------------
CREATE TABLE settings (
    setting_key   VARCHAR(64)  NOT NULL,
    setting_value TEXT         NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every one of these is editable from Admin -> Settings; nothing here is
-- hard-coded anywhere else in the application.
INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name',       'Disruptive Sourcing'),
    ('site_tagline',    'Product catalogue'),
    ('currency_code',   'CAD'),
    ('currency_symbol', '$'),
    ('per_page',        '24'),
    ('price_request_label', 'Price on request'),
    ('contact_email',   ''),
    ('contact_phone',   '+1 (236) 516-8502');

-- ---------------------------------------------------------------------------
-- No origins, categories or products are seeded here. This file is structure
-- and site settings only; the catalogue itself lives in sql/catalogue_data.sql
-- and is imported second. Seeding origins in both files meant the second
-- import hit a duplicate primary key, aborted, and left an empty catalogue
-- behind - so they are defined in exactly one place.
-- ---------------------------------------------------------------------------
