-- ---------------------------------------------------------------------------
-- Database-Driven Product Catalogue - schema
-- MySQL 5.7+ / 8.x / MariaDB 10.3+
-- ---------------------------------------------------------------------------
-- Import with:  mysql -u USER -p DATABASE < sql/schema.sql
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS enquiry_items;
DROP TABLE IF EXISTS enquiries;
DROP TABLE IF EXISTS product_pricing;
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
-- There is deliberately NO price column here. The catalogue is buyer-facing
-- and carries no pricing at all; commercial terms live in product_pricing,
-- which is admin-only. Keeping them in a separate table rather than behind a
-- "show prices" flag means the public queries do not select the figures in the
-- first place, so no setting, template edit or stray var_dump can leak one.
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
-- Internal pricing. NOT public. Nothing outside the admin panel reads this
-- table, and ProductRepository's public methods never join it.
--
-- One row per product at most, so the product id is the primary key - there is
-- no separate id to get out of step with the product. A product with no row
-- here simply has no internal price yet.
--
-- A figure on its own is meaningless on an export line, so the terms that
-- qualify it live beside it: what the price is per, the minimum order, the
-- incoterm it assumes and how long it holds. price_unit and incoterm are free
-- text rather than enums because the useful vocabulary here is the client's,
-- not something worth a migration every time a new packing format appears.
-- ---------------------------------------------------------------------------
CREATE TABLE product_pricing (
    product_id  INT UNSIGNED  NOT NULL,
    price       DECIMAL(12,2) NULL,
    currency    CHAR(3)       NOT NULL DEFAULT 'CAD',
    price_unit  VARCHAR(60)   NULL,      -- per kg, per 10kg carton, per 20ft FCL
    moq         VARCHAR(60)   NULL,      -- minimum order quantity, as written
    incoterm    VARCHAR(20)   NULL,      -- FOB Bangkok, CIF Vancouver, EXW...
    valid_until DATE          NULL,
    supplier    VARCHAR(160)  NULL,
    notes       VARCHAR(400)  NULL,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    KEY idx_pricing_valid (valid_until),
    CONSTRAINT fk_pricing_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Buyer enquiries. A buyer shortlists items as they browse, then submits the
-- shortlist with their details. The shortlist itself lives in the browser
-- until that point - nothing is written here until someone presses send, so
-- browsing leaves no record and there is no session or login to maintain.
--
-- reference is the human handle ("DS-2608-0042") used in correspondence, kept
-- separate from the auto-increment id so ids are never quoted at a buyer.
-- ---------------------------------------------------------------------------
CREATE TABLE enquiries (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference    VARCHAR(24)  NOT NULL,
    company      VARCHAR(160) NULL,
    contact_name VARCHAR(120) NOT NULL,
    email        VARCHAR(190) NOT NULL,
    phone        VARCHAR(60)  NULL,
    country      VARCHAR(120) NULL,
    destination  VARCHAR(160) NULL,      -- port or city they want it delivered to
    incoterm     VARCHAR(20)  NULL,
    message      TEXT         NULL,
    status       ENUM('new','in_progress','quoted','closed') NOT NULL DEFAULT 'new',
    admin_notes  TEXT         NULL,
    ip_address   VARBINARY(16) NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enquiries_reference (reference),
    KEY idx_enquiries_status (status, created_at),
    KEY idx_enquiries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- The lines on an enquiry.
--
-- product_id is nullable and set to NULL if the product is later deleted, but
-- the name and SKU are SNAPSHOT here as text. An enquiry is a record of what
-- someone actually asked for; if it only pointed at products, deleting a
-- discontinued line would quietly rewrite last month's enquiries into
-- something the buyer never sent.
-- ---------------------------------------------------------------------------
CREATE TABLE enquiry_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    enquiry_id   INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NULL,
    product_name VARCHAR(200) NOT NULL,
    product_sku  VARCHAR(64)  NULL,
    quantity     VARCHAR(60)  NULL,      -- "2 x 40ft", "500 kg" - buyer's words
    notes        VARCHAR(300) NULL,
    sort_order   INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_enquiry_items_enquiry (enquiry_id, sort_order),
    KEY idx_enquiry_items_product (product_id),
    CONSTRAINT fk_enquiry_items_enquiry FOREIGN KEY (enquiry_id)
        REFERENCES enquiries (id) ON DELETE CASCADE,
    CONSTRAINT fk_enquiry_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE SET NULL
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
    ('contact_phone',   '+1 (236) 516-8502'),
    ('enquiry_notify_email', ''),
    ('enquiry_intro',   'Tell us where the goods are going and roughly what volume you need, and we will come back with pricing and lead times.');

-- ---------------------------------------------------------------------------
-- No origins, categories or products are seeded here. This file is structure
-- and site settings only; the catalogue itself lives in sql/catalogue_data.sql
-- and is imported second. Seeding origins in both files meant the second
-- import hit a duplicate primary key, aborted, and left an empty catalogue
-- behind - so they are defined in exactly one place.
-- ---------------------------------------------------------------------------
