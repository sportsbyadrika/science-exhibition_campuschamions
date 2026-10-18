# Sahodaya Science Fair Portal

Registration and management portal for the **CBSE South Zone Sahodaya Complex Science Exhibition**.
It is built with plain PHP 8.1+, MySQL/MariaDB and Tailwind CSS v4. It uses **PHPMailer** for email and **Dompdf** for PDF reports.

Page URLs never show the `.php` extension, for example `/register`, `/institution/dashboard` and `/admin/events`.

## User roles

| Role | How it is created | Can do |
|------|------------------|--------|
| **Super Admin** | CLI installer only | Everything an admin can do, plus the activity log. Never listed in, or editable from, the Users screen. |
| **Administrator** | Super admin / admin (Users → Add Administrator) | Settings, masters (events, categories, food coupon heads), institutions, approvals, users, reports |
| **Institution (e-Admin)** | Self-registration on the public **Participation Request** page | Team entries, food coupon requests, fund transfer details, final submission, dashboard and PDF reports |

## Workflow

1. A school submits a **Participation Request** (`/register`) with its own captcha. Emails go to the coordinator and the principal.
2. **Auto-approval** (default, Admin → Settings) approves the request immediately so the school can sign in at once. Admins can still **Reject** it from **Institutions** at any time. The school is signed out straight away and notified by email with the reason, and **Approve** restores access. With auto-approval switched off, requests wait for manual approval.
3. The institution coordinator signs in with the coordinator email and password, then:
   * **Add Team Participant**: chooses an event and category, and adds students with a class and a cropped passport photo. Team size, allowed categories, the per-school limit and the fee all come from the Event master.
   * **Food Coupons**: enters a quantity for each coupon head (Breakfast, Lunch …) at the rate set by the admin.
   * **Fund Transfer**: sees the bank details and records the UTR, amount, date and a receipt upload. More than one transfer is allowed.
   * **Final Submission**: works through a checklist and a declaration, then submits. This locks the entries and emails an acknowledgement with the application PDF attached.
4. Admins approve or reject **team entries** (one at a time or in bulk) and **fund requests**. They can set an approved amount that differs from the claimed one. They can also reopen a submission.
5. **Results**: admins enter positions (First, Second, Third or Special Mention), a grade and remarks for approved teams under **Results**. Clicking **Publish** shows them on the public `/results` page, which is linked from the home page.
6. PDF reports for both roles all have a repeating header, a footer and `Page X of Y` numbering.

## Installation

```bash
# 1. PHP dependencies (PHPMailer, Dompdf)
composer install --no-dev   # only needed if you change composer.json; vendor/ is committed

# 2. Configuration
cp .env.example .env      # set APP_URL, DB_* and (optionally) MAIL_* values

# 3. Database: create an empty DB, then run the installer (creates tables, seed masters, super admin)
mysql -u root -e "CREATE DATABASE science_fair CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php bin/install.php --super-email=owner@example.org --super-pass='StrongPass123' \
                    --admin-email=admin@example.org --admin-pass='StrongPass123' --admin-name='Event Admin'

# 4. Make storage writable by the web server
chmod -R 775 storage
```

Then sign in at `/` with the admin account. Open **Settings** and fill in the event details, the bank account and SMTP. Use **Send Test Email** to check the SMTP settings.

### Configuration (.env)

All server-specific values are read from `.env` in the project root, next to `index.php`. `config/config.php` only maps these to defaults. `.env` is git-ignored and blocked from web access.

| Key | Purpose |
|-----|---------|
| `APP_URL`, `APP_ENV`, `APP_TIMEZONE` | Public URL (no trailing slash); `production` hides PHP errors |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection |
| `UPLOAD_MAX_PHOTO_KB`, `UPLOAD_MAX_RECEIPT_KB` | Upload limits |
| `MAIL_ENABLED`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | PHPMailer SMTP. When `MAIL_HOST` is set, these values override the SMTP fields in Admin → Settings and those fields become read-only there. |

### cPanel deployment (Git Version Control)

* Repository clone: `/home/sportsby/repositories/science_excibision_campuschamions`
* Web root (document root of the domain): `/home/sportsby/science_expo.sportsmis.com`

`.cpanel.yml` runs `bin/deploy.sh`, which does the following:
1. Copies the application files into the web root. It never overwrites `.env`, `vendor/`, `storage/uploads`, `storage/logs` or `storage/cache`, and it never deletes anything already in the web root.
2. Creates the writable `storage/` folders.
3. Copies the committed `vendor/` folder (PHPMailer and Dompdf), so the server does not need Composer. Composer runs only as a fallback when `vendor/` is missing.
4. If the web root has no `.env`, copies `.env.example` there (chmod 600) so you can edit it.

First deployment:
1. In cPanel → **MySQL Databases**, create the database and user and grant all privileges.
2. In cPanel → **Git Version Control**, clone the repository to the path above. Open **Manage → Pull or Deploy**, then **Update from Remote** and **Deploy HEAD Commit**.
3. Edit `/home/sportsby/science_expo.sportsmis.com/.env` with the real DB and mail values.
4. In cPanel → **Terminal**, run the installer once:
   ```bash
   cd /home/sportsby/science_expo.sportsmis.com
   /opt/cpanel/ea-php83/root/usr/bin/php bin/install.php --super-email=you@example.org --super-pass='StrongPass123'
   ```
5. Make sure the domain uses PHP 8.1 or newer (cPanel → **MultiPHP Manager**).

For later releases, push to GitHub, then in cPanel click **Update from Remote** → **Deploy HEAD Commit**. The deploy log is in `storage/logs/deploy.log`.

**No Terminal access?** Use the browser installer instead of step 4. Add `INSTALL_KEY=<long random secret>` to `.env` and open `https://<your-domain>/install`. Enter the key and create the super admin (and optionally the first administrator). Then remove `INSTALL_KEY` from `.env`. The installer turns itself off once a super admin exists.

### Troubleshooting (HTTP 500 / setup page)

The portal checks its set-up on every request and shows a **"setup required"** page that says what is wrong:

| Message | Fix |
|---------|-----|
| needs PHP 8.1 or newer | cPanel → MultiPHP Manager → choose PHP 8.1–8.3 for the domain |
| .env configuration file is missing | Copy `.env.example` to `.env` in the web root and fill it in |
| vendor folder is missing | `composer install --no-dev` in the web root (or re-deploy) |
| storage folder is not writable | `chmod -R 755 storage` |
| Cannot connect to the database: … | Check `DB_*` in `.env`. On cPanel the DB and user names carry the account prefix (`sportsby_…`), and the user must be added to the DB with ALL PRIVILEGES |
| database tables are not installed | Run the installer (`/install` or `bin/install.php`) |

Every error is also logged to `storage/logs/php-error.log`. Set `APP_ENV=development` in `.env` temporarily to show the full error in the browser.

### Front-end assets

The compiled Tailwind CSS (`assets/css/app.css`) and the browser libraries (`assets/vendor/`: Lucide icons, Cropper.js) are committed, so the server does not need Node. After you change markup or styles, run:

```bash
npm install
npm run build      # Tailwind v4 → assets/css/app.css, copies vendor JS
npm run watch      # during development
```

### Local development

```bash
php -S localhost:8000 router.php      # router.php mimics the .htaccess clean-URL rules
```

### Apache

`.htaccess` handles clean URLs. It maps `/page` to `page.php` and 301-redirects any `*.php` URL to its extension-less form. It also blocks `includes/`, `config/`, `database/`, `storage/`, `vendor/` and `bin/`. `mod_rewrite` must be enabled with `AllowOverride All`. If the site lives in a sub-folder, update `RewriteBase` and `app.url`.

### Nginx

```nginx
root /var/www/sciencefair;
index index.php;
location ~ ^/(includes|config|database|storage|vendor|bin|node_modules)/ { deny all; }
location ~ /\. { deny all; }
location / { try_files $uri $uri/ @php; }
location @php { rewrite ^/(.+?)/?$ /$1.php last; }
location ~ \.php$ {
    if ($request_uri ~ ^/(.+)\.php(\?|$)) { return 301 /$1$is_args$args; }
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

Payment receipts can be up to 5 MB, so `upload_max_filesize` must be at least 8M. `.user.ini` and `.htaccess` already set this for most hosts.

## Project structure

```
index.php, register.php, forgot-password.php, reset-password.php   public pages
captcha.php, media.php, logout.php                                 captcha image, protected uploads, sign out
institution/   dashboard, teams, team-form, food-coupons, payments, submit, reports, report (PDF), profile
admin/         dashboard, institutions, institution, teams, payments, food-requests, reports, report (PDF),
               events, event-form, categories, food-items, users, settings, activity (super admin), profile
includes/      bootstrap, db, auth, captcha, mailer (PHPMailer), pdf (Dompdf), services, reports, ui, layouts
database/      schema.sql (tables + seed data)
storage/       uploads (photos, receipts; served only through /media after an access check), logs, cache
assets/        src/app.css (Tailwind source), css/app.css (built), js/, vendor/
```

## Database upgrades

New versions can add tables. `includes/migrations.php` upgrades an existing database automatically on the first request after a deploy, and the version is stored in `settings.schema_version`. No manual SQL is needed.

## Security notes

* Every form has a CSRF token. All queries are PDO prepared statements. Passwords use `password_hash`.
* The image captcha is generated in-house with GD and checked against the session (single use, 15-minute expiry). It protects login, registration and forgotten-password requests.
* Uploads are checked by MIME type, and photos are re-encoded through GD. Files are kept outside the public folders and served by `media.php` only to their owning institution or to admins.
* Password reset tokens are stored hashed and expire after 60 minutes.
