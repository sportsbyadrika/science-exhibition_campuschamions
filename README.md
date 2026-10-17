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
2. An admin approves the request (**Institutions**). Approval can be switched off in Settings so schools can log in straight away.
3. The institution coordinator signs in with the coordinator email and password, then:
   * **Add Team Participant**: chooses an event and category, and adds students with a class and a cropped passport photo. Team size, allowed categories, the per-school limit and the fee all come from the Event master.
   * **Food Coupons**: enters a quantity for each coupon head (Breakfast, Lunch …) at the rate set by the admin.
   * **Fund Transfer**: sees the bank details and records the UTR, amount, date and a receipt upload. More than one transfer is allowed.
   * **Final Submission**: works through a checklist and a declaration, then submits. This locks the entries and emails an acknowledgement with the application PDF attached.
4. Admins approve or reject **team entries** (one at a time or in bulk) and **fund requests**. They can set an approved amount that differs from the claimed one. They can also reopen a submission.
5. PDF reports for both roles all have a repeating header, a footer and `Page X of Y` numbering.

## Installation

```bash
# 1. PHP dependencies (PHPMailer, Dompdf)
composer install --no-dev

# 2. Configuration
cp config/config.example.php config/config.php      # set DB credentials + app.url

# 3. Database: create an empty DB, then run the installer (creates tables, seed masters, super admin)
mysql -u root -e "CREATE DATABASE science_fair CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php bin/install.php --super-email=owner@example.org --super-pass='StrongPass123' \
                    --admin-email=admin@example.org --admin-pass='StrongPass123' --admin-name='Event Admin'

# 4. Make storage writable by the web server
chmod -R 775 storage
```

Then sign in at `/` with the admin account. Open **Settings** and fill in the event details, the bank account and SMTP. Use **Send Test Email** to check the SMTP settings.

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

## Security notes

* Every form has a CSRF token. All queries are PDO prepared statements. Passwords use `password_hash`.
* The image captcha is generated in-house with GD and checked against the session (single use, 15-minute expiry). It protects login, registration and forgotten-password requests.
* Uploads are checked by MIME type, and photos are re-encoded through GD. Files are kept outside the public folders and served by `media.php` only to their owning institution or to admins.
* Password reset tokens are stored hashed and expire after 60 minutes.
