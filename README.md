# Nettoyeur Villeneuve Invoice Portal

A production-oriented Laravel invoice portal for Nettoyeur Villeneuve / Nettoyeurs Villeneuve. The app supports hotel/commercial clients, daily valet records, monthly invoice grids, PDF generation, client invoice access, CSV exports, audit logs, and shared-hosting deployment where private Laravel files stay outside `public_html`.

## Assumptions

- Laravel 11, PHP 8.2+, MySQL/MariaDB, Blade, Tailwind CSS, Alpine.js, and `barryvdh/laravel-dompdf` are used.
- Production runtime requires only PHP and MySQL/MariaDB. Node/Vite is only for building assets before upload.
- Money is stored as integer cents and formatted through `App\Services\MoneyFormatter`.
- Seed invoice examples preserve the requested clients, categories, tax behavior, discounts/credits, notes, and approximate legacy grand totals.
- OCR is not part of the MVP. Uploaded handwritten valet sheets are stored as attachments and must be reviewed by a human.

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL/MariaDB database, then update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nettoyeur_villeneuve
DB_USERNAME=root
DB_PASSWORD=
```

Run the database and frontend setup:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://localhost:8000`.

## Demo Logins

- `admin@example.com` / `password` / Super Admin
- `employee@example.com` / `password` / Employee
- `lordelgin@example.com` / `password` / Client
- `marriott@example.com` / `password` / Client
- `casino@example.com` / `password` / Client

Change these passwords before production use.

## What Is Included

- Public website with branding, services, contact, and login.
- Role-based authentication for Super Admin, Employee, and Client users.
- Admin dashboard with invoice counts, monthly revenue, recent records, and quick actions.
- Business settings for legal name, display name, tax numbers, logo, payment instructions, and thank-you text.
- Clients/hotels with active/archive state, language, tax profile, and custom invoice categories.
- Daily Valet Record form that resembles the paper source record, with rows for name, room/reference, description, category, and charges.
- Reviewed daily records can be aggregated into monthly invoices.
- Manual monthly grid entry supports rows 1-31 and dynamic client category columns.
- Discounts, credits, and fees at invoice or category level.
- Québec TPS/TVQ and Ontario HST/GST-style tax profiles.
- PDF generation with Dompdf and portrait/landscape selection based on category count.
- Client portal limited to invoices for the logged-in client.
- CSV exports for invoice lists and invoice details.
- Upload support for logos and daily record attachments.
- Audit logs for key create/update/review/approval/PDF/payment actions.

## Shared Hosting With SSH

Preferred secure structure:

```text
domains/appvilleneuve.webactiondemo.ca/
├── app_core/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── artisan
│   ├── composer.json
│   ├── composer.lock
│   └── .env
└── public_html/
    ├── index.php
    ├── build/
    ├── .htaccess
    └── storage
```

1. Upload all Laravel private files to `domains/appvilleneuve.webactiondemo.ca/app_core`.
2. Upload only the contents of Laravel `public/` to `domains/appvilleneuve.webactiondemo.ca/public_html`.
3. Replace `public_html/index.php` with `deployment/cpanel/public_html_index_example.php`.
4. Create a MySQL/MariaDB database and user in cPanel/DirectAdmin/RapideNET.
5. Edit `app_core/.env` with production database credentials, `APP_URL`, and `APP_DEBUG=false`.
6. From `app_core`, run:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set writable permissions for:

```text
app_core/storage
app_core/bootstrap/cache
```

Use the most restrictive permissions that work on the host, commonly `775` for directories on shared hosting.

## Shared Hosting Without SSH

1. Run locally:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --seed
```

2. Upload private files, including `vendor/`, to:

```text
domains/appvilleneuve.webactiondemo.ca/app_core
```

3. Upload only Laravel `public/` contents, including `build/`, to:

```text
domains/appvilleneuve.webactiondemo.ca/public_html
```

4. Replace `public_html/index.php` with `deployment/cpanel/public_html_index_example.php`.
5. Create the database and user in the hosting panel.
6. Edit `app_core/.env` manually:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://appvilleneuve.webactiondemo.ca
DB_DATABASE=hosting_db_name
DB_USERNAME=hosting_db_user
DB_PASSWORD=hosting_db_password
```

7. If artisan cannot run on the server, export your seeded local database to SQL and import it through phpMyAdmin. See `deployment/cpanel/manual_sql_import_notes.md`.

## Storage Symlink Fallback

Preferred:

```bash
php artisan storage:link
```

If symlinks are disabled, create `public_html/storage` manually and upload public storage files there when needed. Generated invoice PDFs are stored under `storage/app/public/invoices`; without symlinks, copy or upload that folder to `public_html/storage/invoices` after generation if direct public URLs are needed. Authenticated downloads still use Laravel storage responses.

## Security Notes

- Do not place `.env`, `app_core`, `vendor`, `storage/logs`, `database`, or source files in `public_html`.
- `public_html` should contain only Laravel `public/` files.
- Use `APP_DEBUG=false` in production.
- Keep seeded demo accounts only for initial testing and change passwords immediately.
- Uploads are validated by MIME type and size.
- Client portal controllers enforce `client_id` isolation before viewing or downloading invoices.
- CSRF protection, hashed passwords, Laravel auth sessions, and role middleware are used.

## Future OCR Phase

Future OCR can allow uploading handwritten valet record photos and using AI/OCR to pre-fill rows. Human review must remain required before saving or billing. OCR output must not become the source of truth for billing without validation.

## Limitations

- Composer/PHP were not available in this shell, so dependencies and tests could not be executed here.
- User management screens are intentionally minimal in this MVP seed; roles and seeded users exist, and full user CRUD is the natural next module.
- Custom tax-rate management screens are not expanded yet; the data model and calculation service support custom rates.
