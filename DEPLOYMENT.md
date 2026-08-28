# Deployment

Two routes: **shared hosting / cPanel** (most likely) and **a VPS with nginx**.
Both take about fifteen minutes.

---

## A. Shared hosting or cPanel

### 1. Create the database

In cPanel: **MySQL Databases**.

1. Create a database, e.g. `myaccount_catalogue`.
2. Create a user with a long random password.
3. Add the user to the database with **All Privileges**.

Write down the three values — database name, username, password. On cPanel the
real names are usually prefixed with your account name.

### 2. Import the tables

cPanel → **phpMyAdmin** → select your database → **Import** tab.

1. Import `sql/schema.sql` — tables and site settings.
2. Import `sql/catalogue_data.sql` — the origins, the eight categories and the
   full product list.

Import them in that order. The second file expects the tables from the first.
Skip the second only if you want to start from an empty catalogue and type
everything in yourself.

Both together should give you 8 categories, 5 origins, 197 products and 213
image rows. If the second import reports an error part-way through, empty the
database and start again rather than importing it twice — a half-finished
import leaves gaps that are hard to spot later.

`schema.sql` also creates `product_pricing` (your internal figures) and
`enquiries` / `enquiry_items` (buyer enquiries). All three start empty and are
filled through the admin panel, so there is nothing to import for them.

**Do not run `tools/load_catalogue.php` on a live site.** It rebuilds the
catalogue from scratch and would take your price sheet and stored enquiries
with it. It refuses to run once either of those has data in it, but the safe
habit is to treat it as a first-install tool only and use the admin panel
afterwards.

### 3. Upload the files

The important decision is where the domain points.

**On a subdomain, alongside a main site you are not touching:**

This is the usual case when the main site is on a closed platform (Wix,
Squarespace, Shopify) that cannot run PHP. The two live side by side: the
platform keeps the apex domain, the catalogue gets a subdomain such as
`catalogue.example.com`.

In cPanel → **Domains** → *Create a domain*, enter the subdomain and set its
document root. cPanel offers `public_html/catalogue` by default; point it at
the project's `public/` folder instead (see below). Creating the subdomain here
also writes the DNS record automatically **if the domain's nameservers are the
host's**. If DNS is managed elsewhere — a registrar, Cloudflare, or the site
platform itself — cPanel cannot write it, and the subdomain will not resolve
until an `A` record for it is added there by hand, pointing at the hosting
account's IP address (cPanel shows it under *Server Information*).

Adding a subdomain does not affect the apex domain, `www`, or mail. It is
purely additive, so it can be done while the main site is live.

Then issue the certificate for the subdomain specifically — cPanel's *SSL/TLS
Status* page, **Run AutoSSL**. A certificate covering `example.com` does not
cover `catalogue.example.com`.

**Preferred — point the domain at `public/`:**

Upload the whole project somewhere *outside* the web root, e.g.
`/home/youraccount/catalogue/`, then in cPanel → **Domains** set the document
root of your domain to `/home/youraccount/catalogue/public`.

This is the safest layout: `app/` (which contains your database password) is
not reachable from the internet at all.

**Fallback — everything in `public_html`:**

If your host will not let you change the document root, upload the whole
project into `public_html/` instead. The `.htaccess` in the project root then
serves the site out of `public/` and blocks direct access to `app/`, `sql/`,
`tools/` and `db/`.

Either way, make sure the **hidden files are uploaded** — `.htaccess` in the
project root, in `public/` and in `public/uploads/`. Most FTP clients hide
dot-files until you switch the option on. The one in `public/uploads/` is what
stops an uploaded file from ever being executed, so it is not optional.

### 4. Configuration

Rename `app/config.sample.php` to `app/config.php` and fill in:

```php
'db' => [
    'host' => 'localhost',
    'name' => 'myaccount_catalogue',
    'user' => 'myaccount_catuser',
    'pass' => 'the-password-you-created',
],
...
'debug' => false,          // must stay false on a live site
```

Set `'https_only' => true` under `security` once your SSL certificate is
working, so the admin session cookie is only ever sent over HTTPS.

### 5. Permissions

```
public/uploads        755  (needs to be writable by the web server)
app/config.php        644
```

If image uploads fail with "Upload folder is not writable", set
`public/uploads` to `775`. Only go to `777` if your host insists — it means
any account on that server can write there.

### 6. Create your admin login

If you have SSH:

```
php tools/create_admin.php yourname "a long password you will remember"
```

**No SSH?** Use cPanel's *Terminal*, or its *Cron Jobs* page: add a cron job
with the command below, set it to run in a minute's time, then delete the job
once it has run.

```
/usr/local/bin/php /home/youraccount/catalogue/tools/create_admin.php yourname "your password"
```

If neither is available, tell me the username and I will send you a single
`INSERT` statement to paste into phpMyAdmin with the password already hashed —
do not put a plain-text password in the database.

### 7. Check it

- `https://yourdomain.com/` — the catalogue
- `https://yourdomain.com/admin` — sign in

Then in **Settings**, change the site name, tagline and currency.

---

## B. VPS with nginx

Document root is the `public/` folder. There is no `.htaccess` on nginx, so the
rewrite and the uploads protection both go in the server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/catalogue/public;
    index index.php;

    client_max_body_size 8M;         # must exceed the upload limit in config.php

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Uploads are data, never code. This is the nginx equivalent of the
    # .htaccess in public/uploads - without it, an uploaded file that somehow
    # ended up with a .php name would be executed.
    location ^~ /uploads/ {
        location ~ \.php$ { return 403; }
    }

    location ~ /\. { deny all; }
}
```

Then:

```
sudo chown -R www-data:www-data /var/www/catalogue/public/uploads
sudo chmod -R 755 /var/www/catalogue/public/uploads
sudo nginx -t && sudo systemctl reload nginx
```

---

## PHP settings for uploads

The application limits images to 6 MB (`uploads.max_bytes` in
`app/config.php`), but PHP itself has the final say. If large photographs are
rejected before the application sees them, raise these in `php.ini` or in
cPanel's *MultiPHP INI Editor*:

```
upload_max_filesize = 8M
post_max_size       = 32M      # several images can be sent in one save
max_file_uploads    = 20
memory_limit        = 256M     # GD needs room to resize a big photo
```

`post_max_size` is the one people forget. If a save silently does nothing when
several large images are attached, that is the setting to raise.

---

## Moving the site later

Everything is in three places:

1. The files.
2. The database (`mysqldump`).
3. `public/uploads/` — the actual image files. A database dump does **not**
   contain them, only their paths.

```
mysqldump -u USER -p DATABASE > catalogue-backup.sql
tar -czf uploads-backup.tar.gz public/uploads
```

Restore in the same order: files, database, uploads, then edit
`app/config.php` for the new server.

---

## Going live checklist

- [ ] `'debug' => false` in `app/config.php`
- [ ] `'https_only' => true` once SSL works
- [ ] Admin password changed from whatever you first set
- [ ] `public/uploads/.htaccess` uploaded (check with your FTP client's
      "show hidden files" option)
- [ ] Site name, tagline and contact details set in **Settings**
- [ ] A backup of the database taken before you start entering real data
- [ ] A test enquiry sent from the public site and confirmed to appear under
      **Admin → Enquiries** — this is the one path a buyer actually uses
- [ ] If you set an **enquiry notification email**, confirm the test enquiry
      actually arrived. `mail()` depends on the host having working outbound
      mail and fails quietly; the enquiry is saved either way, so a missing
      email is easy not to notice
- [ ] Confirm no price appears anywhere public: enter one figure on the price
      sheet, then check the product page, the grid and the search results
