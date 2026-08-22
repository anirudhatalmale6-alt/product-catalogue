# Product Catalogue

A database-driven catalogue for physical goods: a public browsing front-end
with category filtering and search, and an admin panel for adding, editing and
deleting products, categories and images.

Built on **PHP 8 + MySQL** with PDO. No framework, no build step, no Composer
dependencies — upload the files, import two SQL files, edit one config file.

- **Public site:** `/` — catalogue grid, filters, search, product pages
- **Admin panel:** `/admin` — sign in, then manage everything

---

## What is in the box

| Folder | What it holds |
|---|---|
| `public/` | The only folder the web server needs to serve. Front controller, CSS, JS, uploaded images. |
| `app/` | Application code: config, controllers, views and the small library classes. |
| `sql/` | `schema.sql` (tables) and `sample_data.sql` (16 demo products). |
| `tools/` | Command-line helpers: create an admin user, seed the sample data, regenerate placeholder images. |

Full install instructions are in **[DEPLOYMENT.md](DEPLOYMENT.md)**.
A walkthrough of the admin panel is in **[USER-GUIDE.md](USER-GUIDE.md)**.

---

## Quick start (local)

```
mysql -u root -p -e "CREATE DATABASE catalogue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p catalogue < sql/schema.sql
mysql -u root -p catalogue < sql/sample_data.sql

cp app/config.sample.php app/config.php     # then edit the db credentials
php tools/create_admin.php admin "a-long-password-here" "Your Name"

php -S localhost:8000 -t public tools/router_dev.php
```

Open <http://localhost:8000> for the catalogue and
<http://localhost:8000/admin> to sign in.

---

## The data model

```
categories ──< products ──< product_images
                     └────< product_specs
```

**`products`** holds the fields every item has: name, SKU, short and full
description, price, optional sale price, availability, stock quantity, brand,
weight, visibility flags.

**`product_specs`** is the part that makes this scale. Technical
specifications are stored as name/value rows rather than columns, so a drill
can carry "Max torque / 70 Nm" and a helmet can carry "Standard / EN 397"
without either one needing a schema change. An optional `spec_group` splits a
long list into sections ("Motor", "Physical", "Certification") on the product
page.

**`product_images`** allows any number of images per product. Exactly one row
per product carries `is_primary = 1` — that is the one used on the catalogue
grid. If the main image is deleted, the next one is promoted automatically.

**`categories`** can nest via `parent_id`. Filtering a parent category also
returns products filed under its children. Deleting a category does **not**
delete its products — they become uncategorised — because losing stock records
while tidying up categories would be a nasty surprise.

Availability is an enum: `in_stock`, `low_stock`, `out_of_stock`, `preorder`,
`discontinued`.

---

## Security

The things that matter on a public-facing site, and where they are handled:

| Concern | Where |
|---|---|
| SQL injection | Every query uses PDO prepared statements with bound parameters (`app/lib/Database.php`). Sort order comes from a whitelist, never from the URL. |
| Cross-site scripting | Every value printed in a view goes through `e()` (`htmlspecialchars`). |
| Cross-site request forgery | Every state-changing POST carries a token checked by `csrf_check()`. |
| Password storage | `password_hash()` bcrypt, rehashed automatically when PHP's cost changes. |
| Brute force | Failed sign-ins are counted per IP and locked out after 8 attempts in 15 minutes (`login_attempts` table). |
| Malicious uploads | Every image is re-encoded through GD, which strips anything hidden inside it. MIME type is read from the file contents, not the filename. Filenames are generated, never taken from the browser. |
| Executing an upload | `public/uploads/.htaccess` turns the PHP engine off and refuses to serve anything that is not an image. |
| Session fixation | The session id is regenerated on sign-in; cookies are HttpOnly and SameSite=Lax. |
| Credential exposure | `app/config.php` is outside the web root and git-ignored. |

The included test run covers these: a `.php` file renamed to `.jpg` is
rejected, and a POST without a CSRF token returns 419 without touching the
database.

---

## Requirements

- PHP 8.0 or newer with `pdo_mysql`, `gd`, `fileinfo` (all standard)
- MySQL 5.7+ / 8.x, or MariaDB 10.3+
- Apache with `mod_rewrite`, or nginx (a `try_files` rule is in DEPLOYMENT.md)

Pretty URLs are optional — with rewriting switched off the site still works,
the addresses just look like `index.php?r=product/slug`.
