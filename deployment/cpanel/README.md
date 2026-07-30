# cPanel / DirectAdmin / RapideNET Deployment

## Mise à jour des catalogues partagés

Après avoir téléversé les fichiers privés modifiés dans `app_core`, lancer depuis ce dossier:

```bash
php artisan optimize:clear
php artisan app:apply-shared-catalogs --force
php artisan app:apply-employee-catalog --force
php artisan view:cache
```

La première commande de catalogue copie le catalogue actif Holiday Inn vers les hôtels configurés et applique le modèle commerces Ottawa. La seconde ajoute la section `EMPLOYÉS` aux hôtels admissibles sans remplacer leur catalogue; Hilton Lac-Leamy est exclu. Elles conservent les profils de taxes propres à chaque client. Régénérer ensuite les PDF concernés pour obtenir la présentation compacte des grands catalogues.

This app is designed for two hosting layouts.

## Mode 1: Document Root Points To Laravel `public`

Use this if the hosting panel lets you set the domain document root.

Set the document root to:

```text
domains/appvilleneuve.webactiondemo.ca/app_core/public
```

Upload the full Laravel app to `app_core`, run Composer and artisan commands there, and leave `.env`, `vendor`, `storage`, `config`, `database`, and source code outside any public web root.

## Mode 2: Fixed `public_html`

Use this if the hosting panel forces:

```text
domains/appvilleneuve.webactiondemo.ca/public_html
```

Upload private Laravel files to:

```text
domains/appvilleneuve.webactiondemo.ca/app_core
```

Upload only the contents of Laravel `public/` to:

```text
domains/appvilleneuve.webactiondemo.ca/public_html
```

Replace `public_html/index.php` with `deployment/cpanel/public_html_index_example.php`. The important paths are:

```php
require __DIR__.'/../app_core/vendor/autoload.php';
$app = require_once __DIR__.'/../app_core/bootstrap/app.php';
```

Never upload `.env`, `composer.json`, `vendor`, `app`, `routes`, `storage`, or `database` into `public_html`.
