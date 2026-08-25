# Disruptive Sourcing — Product Catalogue

A database-driven catalogue for physical goods: a public browsing front-end
with category and origin filtering plus search, and an admin panel for adding,
editing and deleting products, categories, origins and images.

Built on **PHP 8 + MySQL** with PDO. No framework, no build step, no Composer
dependencies — upload the files, import two SQL files, edit one config file.

- **Public site:** `/` — catalogue grid, filters, search, product pages
- **Admin panel:** `/admin` — sign in, then manage everything

Styled to the Disruptive Sourcing brand standards: Industrial Black `#0E0E0E`,
Performance Red `#C8102E`, Carbon Gray `#2B2B2B`, Steel Gray `#6A6A6A`,
Engineering White `#F5F5F5`, Roboto and Roboto Mono. The two font files are
self-hosted in `public/assets/fonts/` — nothing is fetched from a third party
at run time.

---

## What is in the box

| Folder | What it holds |
|---|---|
| `public/` | The only folder the web server needs to serve. Front controller, CSS, JS, fonts, brand artwork, uploaded images. |
| `app/` | Application code: config, controllers, views and the small library classes. |
| `sql/` | `schema.sql` (tables and site settings) and `catalogue_data.sql` (the product list). |
| `tools/` | Command-line helpers: create an admin user, load the catalogue, regenerate placeholder images. |

Full install instructions are in **[DEPLOYMENT.md](DEPLOYMENT.md)**.
A walkthrough of the admin panel is in **[USER-GUIDE.md](USER-GUIDE.md)**.

---

## Quick start (local)

```
mysql -u root -p -e "CREATE DATABASE catalogue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p catalogue < sql/schema.sql
mysql -u root -p catalogue < sql/catalogue_data.sql

cp app/config.sample.php app/config.php     # then edit the db credentials
php tools/create_admin.php admin "a-long-password-here" "Your Name"

php -S localhost:8000 -t public tools/router_dev.php
```

Import the two SQL files in that order. `schema.sql` creates the tables and the
site settings and seeds nothing else; `catalogue_data.sql` carries the origins,
categories and products.

Open <http://localhost:8000> for the catalogue and
<http://localhost:8000/admin> to sign in.

---

## The data model

```
categories ──< products ──< product_images
origins    ──<    │    └──< product_specs
```

**`products`** holds the fields every item has: name, SKU, short and full
description, price, optional sale price, availability, stock quantity, brand,
weight, visibility flags.

**Price is nullable, on purpose.** On an export catalogue the figure depends on
volume and incoterm, so an item with no price is not an incomplete record — it
is a "price on request" line. The grid and the product page say so (the wording
is editable under Settings), the price filter skips those rows rather than
treating them as `0.00`, and the two price sorts push them to the end instead
of letting them lead a "low to high" list.

**`origins`** is country of origin, and it is deliberately a separate dimension
from categories rather than a second category tree. A buyer wants "Thai frozen
items", not a Thailand branch duplicated under every product type — so origin
cuts across all of them, has its own filter and its own `/origin/thailand`
page. Deleting an origin leaves its products in place with no origin set, and
the sidebar carries a "Not specified" bucket so part-filled imports can be
worked through.

**`product_specs`** is the part that makes this scale. Technical
specifications are stored as name/value rows rather than columns, so a case of
frozen mango can carry "Pack sizes / 10 kg" and a respirator can carry
"Protection level / N95" without either one needing a schema change. An
optional `spec_group` splits a long list into sections ("Packing",
"Logistics", "Compliance") on the product page.

Each category also carries a `spec_template`: the headings a product of that
type normally needs, one per line, optionally as `Group|Heading`. Pick a
category on the product form and those headings appear as blank rows ready to
fill in, so the same fields get used across a whole category instead of being
retyped slightly differently every time. It only fires while the table is
empty, so it can never overwrite anything already entered, and a row with no
value is ignored on save.

**`product_images`** allows any number of images per product. Exactly one row
per product carries `is_primary = 1` — that is the one used on the catalogue
grid. If the main image is deleted, the next one is promoted automatically.

**`categories`** can nest via `parent_id`. Filtering a parent category also
returns products filed under its children. Deleting a category does **not**
delete its products — they become uncategorised — because losing stock records
while tidying up categories would be a nasty surprise.

Availability is an enum: `in_stock`, `low_stock`, `made_to_order`, `preorder`,
`out_of_stock`, `discontinued`. `made_to_order` displays as "Available to
order" and is the default — for sourcing lines that is honest, where claiming
"In stock" for two hundred items would not be.

---

## What is NOT in here

Worth stating plainly, because a catalogue that looks complete invites the
assumption that it is:

- **No prices.** Every product loaded from the source list has none and shows
  "Price on request". Add them in the admin panel where you want them.
- **No pack sizes, shelf lives, minimum orders, certifications or HS codes.**
  None were supplied, and each is a commercial claim that would be published
  under your name, so nothing was invented. The category spec templates lay out
  the headings; the values are yours to enter.
- **No photographs.** `public/uploads/products/catalogue/` holds generated
  placeholder plates that say "image to follow". Uploading a real image against
  a product replaces its placeholder.
- **No cart, checkout, payments or customer accounts.** This is a catalogue,
  not a shop.
- No product variants, no CSV import, no multi-language.

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
