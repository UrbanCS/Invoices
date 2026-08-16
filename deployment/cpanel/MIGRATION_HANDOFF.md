# Codex App Handoff - Migration to app.nettoyeur-villeneuve.ca

## Objective

Move the existing production Laravel application from:

- `https://appvilleneuve.webactiondemo.ca`

to:

- `https://app.nettoyeur-villeneuve.ca`

The new subdomain already exists and its document root is:

- `public_html/app`

Use the ChatGPT desktop app's built-in Browser. The user must enter all cPanel,
DirectAdmin, database, or other private credentials directly in the browser.
Never ask the user to paste passwords into the chat or save them in this file.

## Local project

- Windows path: `C:\Users\marca\OneDrive\Desktop\Invoices`
- WSL path: `/mnt/c/Users/marca/OneDrive/Desktop/Invoices`
- Framework: Laravel 11
- Runtime: PHP 8.2+ and MySQL/MariaDB
- Frontend assets: Vite build, with no Node process required in production

Read these files before changing or deploying anything:

- `README.md`
- `deployment/cpanel/README.md`
- `deployment/cpanel/manual_sql_import_notes.md`
- `bootstrap/app.php`
- `config/dompdf.php`
- `public/index.php`
- `config/filesystems.php`

## Application status

The application includes:

- Super-admin, employee, and client accounts
- Client isolation for invoices and cleaning orders
- Client item catalogs with fixed prices
- Client cleaning-order submission with quantities only
- Saved employee names and department numbers
- Admin order review, editing, approval, and invoice conversion
- Monthly statements and invoices
- Ontario and Quebec tax calculations
- Adjustments, CSV exports, PDF generation, uploads, and audit logs

The order workflow is:

1. Client submits a cleaning order.
2. Admin reviews or corrects it.
3. Admin approves it.
4. Admin creates the invoice.
5. The order is linked to the invoice and marked invoiced.

Do not weaken controller authorization or client ownership checks.
Money must remain stored as integer cents.

## Client super-admin account to preserve

The client's production administrator must continue to have full access after
the migration:

- Login/email: `nettoyeur.villeneuve@hotmail.com`
- Expected role: `super_admin`
- Expected `client_id`: `null`
- Expected status: active

There is no separate username in this Laravel application; the email address is
the login identifier. Do not put the plaintext password in source control,
deployment archives, browser notes, terminal history, or chat. Importing the
existing production database preserves the current password hash automatically.

During the final smoke test, pause and let the user enter the existing password
directly in the login form. If it is not accepted, securely reset it to a new
strong temporary password, invalidate existing sessions, verify the login, and
give the password directly to the user without recording it in project files or
logs.

## Required safe hosting layout

Use this structure on the new hosting account:

```text
domains/nettoyeur-villeneuve.ca/
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
    └── app/
        ├── index.php
        ├── .htaccess
        ├── build/
        ├── favicon.svg
        └── storage
```

Only the contents of Laravel's `public/` directory belong in
`public_html/app`. Never place `.env`, application source, `vendor`, database
files, or logs inside the public document root.

Because the public directory is nested under `public_html/app`, verify and
adapt all shared-hosting public-path assumptions before deployment:

- `public_html/app/index.php` must load private files through
  `../../app_core/vendor/autoload.php` and `../../app_core/bootstrap/app.php`.
- `bootstrap/app.php` currently detects `../public_html`; it must prefer
  `../public_html/app` for this target.
- `config/dompdf.php` currently resolves `../public_html`; it must resolve
  `../public_html/app` for this target.
- Storage-link creation must target `public_html/app/storage`.

Keep local development compatible with the normal Laravel `public/` directory.

## Migration checklist

1. Inspect the target account and confirm the exact absolute paths before
   uploading or editing anything.
2. Back up the old application's files, `.env`, database, and uploaded storage.
3. Back up any existing files and database on the new account.
4. Make the nested `public_html/app` path support safe and backward compatible.
5. Run available automated tests and build production assets locally.
6. Upload private Laravel files to the new `app_core`.
7. Upload only Laravel `public/` contents to `public_html/app`.
8. Preserve the existing production `APP_KEY`.
9. Create or select the new MySQL/MariaDB database and user.
10. Export the old production database and import it into the new database.
11. Update the new private `.env`:
    - `APP_ENV=production`
    - `APP_DEBUG=false`
    - `APP_URL=https://app.nettoyeur-villeneuve.ca`
    - new database host, port, name, username, and password
    - secure session/cookie settings appropriate for HTTPS
12. Copy `storage/app/public` and any other required uploaded documents or
    generated PDFs.
13. Set writable permissions on `storage` and `bootstrap/cache`.
14. Create the `public_html/app/storage` symlink. If symlinks are unavailable,
    retain authenticated Laravel downloads and document the fallback used.
15. Run migrations without destructive reset commands.
16. Clear stale caches, then cache configuration, routes, and views.
17. Verify HTTPS and test:
    - public landing page and assets
    - admin login
    - `nettoyeur.villeneuve@hotmail.com` has active `super_admin` access
    - client login and client isolation
    - cleaning-order creation
    - admin review, approval, and invoice conversion
    - invoice totals and taxes
    - PDF generation/download
    - CSV export
    - uploads/storage
18. Do not remove or modify the old production site until the new site passes
    the complete smoke test and the user approves the cutover.

## Browser task

After reading this file and inspecting the repository:

1. Open the hosting login in the built-in Browser.
2. Pause so the user can enter the private credentials.
3. Inspect the target account and confirm paths, PHP version, databases, and
   available terminal/SSH features.
4. Implement any required nested-path code changes locally and verify them.
5. Perform the migration end to end, using reversible backups.
6. Report every production action truthfully and never claim success without
   checking the live URL and critical workflows.
