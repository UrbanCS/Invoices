# Manual SQL Import Notes

Use this path when the host does not provide SSH or cannot run `php artisan migrate`.

1. Configure the app locally with the same database engine version as production when possible.
2. Run `php artisan migrate --seed` locally.
3. Export the local database as SQL using phpMyAdmin, MySQL Workbench, or:
   `mysqldump -u root -p nettoyeur_villeneuve > nettoyeur_villeneuve.sql`
4. In the hosting panel, create the MySQL/MariaDB database and user.
5. Open phpMyAdmin in the hosting panel and import `nettoyeur_villeneuve.sql`.
6. Update `app_core/.env` with the production DB name, username, password, host, and `APP_URL`.
7. Change all seeded demo passwords immediately after first login.

If the host allows remote MySQL connections, you may run migrations locally against the remote database by temporarily setting your local `.env` to the remote DB credentials, then running `php artisan migrate --seed`.
