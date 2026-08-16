# Handoff Codex - Nettoyeur Villeneuve

Derniere mise a jour: 2026-08-15

Ce document est la source de contexte principale pour reprendre le projet dans un nouveau chat Codex ou sur un autre ordinateur. Il decrit l'etat local, les regles metier, le deploiement cPanel et les verifications encore necessaires.

## Consignes de reprise obligatoires

1. Lire ce document en entier avant toute modification.
2. Executer `git status --short --branch` avant de toucher aux fichiers.
3. Ne jamais annuler, ecraser ou nettoyer les changements non valides sans l'autorisation explicite de Marc-Aime.
4. Distinguer trois etats differents:
   - le code local dans ce dossier;
   - le dernier commit Git;
   - les fichiers actuellement televerses sur le serveur cPanel.
5. Ne jamais affirmer qu'une modification est en production sans l'avoir verifiee sur le site en direct.
6. Ne jamais copier un mot de passe, une cle `APP_KEY`, des identifiants de base de donnees ou le contenu de `.env` dans un chat, un commit ou ce document.
7. L'argent doit toujours rester stocke en cents entiers. Ne pas introduire de nombres flottants pour les calculs monetaires.

## Emplacement du projet

- Windows: `C:\Users\marca\OneDrive\Desktop\Invoices`
- WSL: `/mnt/c/Users/marca/OneDrive/Desktop/Invoices`
- Depot Git: branche `main`
- Application: Laravel 11, PHP 8.2+, MySQL/MariaDB
- Interface: Blade, Tailwind CSS, Alpine.js et Vite
- PDF: `barryvdh/laravel-dompdf`
- Production: PHP et MySQL seulement; aucun serveur Node, Redis, Docker ou worker permanent

## Etat Git local au 2026-08-15

La copie locale contient des changements non valides importants. Ils representent les dernieres fonctions de distinction entre commandes d'employes et commandes de clients d'hotel. Un simple `git clone` sur un autre ordinateur ne recuperera pas ces changements tant qu'ils ne sont pas commits et pousses. Il faut donc synchroniser tout le dossier OneDrive, creer une archive du projet ou faire un commit intentionnel avant le transfert.

Fichiers modifies ou non suivis au moment de ce handoff:

```text
M  app/Http/Controllers/AccountStatementController.php
M  app/Http/Controllers/AdminCleaningOrderController.php
M  app/Http/Controllers/MonthlyInvoiceController.php
M  app/Http/Controllers/PortalOrderController.php
M  app/Models/CleaningOrder.php
M  app/Services/CsvExportService.php
M  app/Services/DailyRecordAggregationService.php
M  app/Services/InvoicePdfService.php
M  resources/views/account-statements/index.blade.php
M  resources/views/monthly-invoices/form.blade.php
M  resources/views/monthly-invoices/index.blade.php
M  resources/views/monthly-invoices/show.blade.php
M  resources/views/pdf/monthly-invoice.blade.php
M  resources/views/portal/orders/create.blade.php
M  resources/views/portal/orders/index.blade.php
M  resources/views/portal/orders/show.blade.php
M  tests/Feature/CleaningOrderInvoiceWorkflowTest.php
M  tests/Feature/InvoiceCatalogAdministrationTest.php
M  tests/Feature/MonthlyInvoiceApprovalWorkflowTest.php
?? app/Services/InvoicePresentationService.php
?? database/migrations/2026_07_30_000001_add_hotel_order_identity_fields.php
?? deployment/cpanel/MIGRATION_HANDOFF.md
```

Toujours refaire `git status` car cette liste peut evoluer.

## Objectif du produit

Nettoyeur Villeneuve travaille avec des hotels et des clients commerciaux. L'application remplace les registres papier et centralise:

- les clients, hotels et commerces;
- leurs catalogues d'items et prix fixes;
- les commandes de nettoyage soumises par les clients;
- les registres valet quotidiens;
- les etats de compte mensuels;
- les factures mensuelles;
- les taxes Ontario et Quebec;
- les ajustements, statuts, PDF, CSV, pieces jointes et journaux d'audit.

L'application est principalement en francais. L'ancienne option de traduction anglaise a ete retiree de l'interface principale.

## Roles et securite

### Super administrateur

- acces complet;
- gestion des clients, catalogues, parametres et comptes portail;
- creation, modification, approbation, envoi, paiement, annulation et suppression de factures;
- revision et correction des commandes;
- generation de factures a partir des commandes;
- acces aux rapports, etats de compte, PDF et CSV.

### Employe Nettoyeur Villeneuve

- tableau de bord;
- registres quotidiens;
- commandes a traiter et correction avant facturation;
- creation et gestion courante des factures;
- pas de gestion globale des utilisateurs ou parametres super administrateur.

### Client ou hotel

- rattache a un seul `client_id`;
- ne voit que ses propres factures et commandes;
- peut creer une commande de nettoyage;
- choisit les items et quantites, mais ne peut jamais changer les prix;
- peut modifier une commande tant qu'elle est encore soumise et non facturee;
- peut telecharger ses propres PDF.

L'isolation par `client_id` doit etre verifiee dans les controleurs et jamais seulement dans les vues. Les prix des commandes sont recalcules cote serveur a partir du catalogue actif.

## Comptes administrateurs a preserver

Les identifiants de connexion connus a verifier dans la base de production sont:

- `appvilleneuve@appvilleneuve.webactiondemo.ca`
- `nettoyeur.villeneuve@hotmail.com`

Ils doivent etre actifs et avoir le role `super_admin` avec `client_id = null` si ces comptes existent toujours. Aucun mot de passe n'est conserve ici. Les mots de passe doivent etre transmis par un canal prive ou reinitialises de facon securisee.

Les comptes `admin@example.com`, `employee@example.com` et les comptes clients du seeder sont uniquement des comptes de demonstration. Le mot de passe de demonstration ne doit pas etre utilise en production.

## Modules fonctionnels actuels

### Authentification et portail client

- connexion et deconnexion Laravel;
- inscription client autorisee seulement lorsque le courriel correspond a un client existant;
- creation ou mise a jour d'un acces portail depuis la fiche du client;
- navigation et redirection selon le role;
- filtrage des factures du portail par annee et statut;
- controle strict afin qu'un client ne puisse jamais changer l'URL pour voir un autre client.

### Clients et catalogues

- creation, modification, archivage et suppression complete par le super administrateur;
- adresse, courriel, taxes, langue, logo et style PDF;
- categories/items actifs avec prix fixe en cents;
- regroupement de catalogue par `service_type`:
  - `dry_cleaning`;
  - `laundry`;
  - `pressing`;
  - `other`;
- classement par `audience`:
  - `gentlemen`;
  - `ladies`;
  - `unisex`;
  - `employees`;
- ordre modifiable avec `sort_order`;
- copie d'un catalogue d'un client a un autre;
- activation en masse;
- modele hotels partage;
- modele commerces Ottawa;
- modele special `EMPLOYES`.

Regle critique: un prix employe doit avoir `audience = employees`. Le mot "Employe" dans le nom d'un item ne suffit pas. Si aucune categorie active avec cette audience n'existe, l'option Employe peut etre absente ou inutilisable.

### Catalogues partages configures

Le fichier `config/shared_catalogs.php` contient:

- la source Holiday Inn Ottawa Downtown - Parliament Hill;
- les hotels cibles qui partagent ce catalogue;
- les commerces Ottawa qui partagent le catalogue du document Word;
- les huit tarifs `EMPLOYES` pour les hotels admissibles;
- l'exclusion explicite de Hilton Lac-Leamy du modele `EMPLOYES`.

Commandes disponibles:

```bash
php artisan app:apply-shared-catalogs --force
php artisan app:apply-employee-catalog --force
```

Attention: `app:apply-shared-catalogs` peut remplacer le catalogue actif de clients cibles. Faire une sauvegarde de la base et verifier les noms cibles avant de l'executer en production. `app:apply-employee-catalog` est concue pour etre relancable et conserve les autres items.

### Commandes de nettoyage client

Le client se connecte puis ouvre `Mes commandes`.

Flux:

1. Le client choisit une date de service.
2. Il choisit un type de commande:
   - `Employe`;
   - `Client de l'hotel`.
3. Pour un employe, il indique le nom, le numero d'etiquette et, au besoin, le numero de departement.
4. Pour un client de l'hotel, il indique le nom du client et le numero de chambre.
5. Il choisit uniquement des items autorises et entre les quantites.
6. Le serveur reprend les prix fixes du catalogue, calcule les lignes et le total, puis enregistre des instantanes.
7. Le client peut reutiliser un nom d'employe sauvegarde ou ajouter un nouveau nom.
8. La commande apparait dans le portail et dans l'administration.

Les commandes d'employes utilisent les categories `audience = employees`. Les commandes des clients de l'hotel utilisent les autres tarifs reguliers. Cette separation doit etre imposee cote serveur.

Statuts des commandes:

- `submitted`: soumise, encore modifiable par le client;
- `reviewed`: approuvee/revisee par Nettoyeur Villeneuve;
- `invoiced`: liee a une facture;
- `cancelled`: annulee.

Les administrateurs/employes peuvent corriger une commande avant sa facturation. Une commande liee a une facture ne doit pas etre refacturee.

### Etats de compte et conversion en facture

- vue mensuelle des transactions par client;
- montant total du mois;
- ajustement administratif avec note;
- commandes a traiter visibles dans la section Factures;
- creation d'une facture pour une commande approuvee;
- creation d'une facture mensuelle regroupant les commandes admissibles du mois;
- les commandes incluses sont liees par `monthly_invoice_id` et passent a `invoiced`;
- les commandes deja facturees ou encore soumises ne doivent pas etre incluses une deuxieme fois.

Les factures creees par l'administration ou depuis les commandes sont approuvees automatiquement par `InvoiceApprovalService` dans les flux qui le demandent. Verifier les tests `MonthlyInvoiceApprovalWorkflowTest` et `CleaningOrderInvoiceWorkflowTest` avant de modifier ce comportement.

### Registres valet quotidiens

- client/hotel, date, reference et notes;
- lignes avec nom, chambre/departement/reference, description, categorie et frais;
- total calcule;
- champs recu par, signature hotel et numero de taxe;
- piece jointe photo/PDF du registre papier;
- statuts brouillon, revise et facture;
- seuls les registres revises peuvent alimenter une facture mensuelle.

### Factures mensuelles

- creation manuelle avec grille jours 1 a 31;
- generation depuis des registres revises;
- generation depuis une ou plusieurs commandes approuvees;
- categories dynamiques et instantanees;
- calcul item x quantite x prix unitaire;
- rabais, credits et frais;
- taxes Ontario ou Quebec;
- statuts brouillon, approuvee, envoyee, payee et annulee;
- suppression definitive reservee au super administrateur;
- regeneration des sources lorsqu'une facture test est supprimee;
- PDF, CSV, pieces jointes et audit.

Les factures et leurs vues distinguent maintenant:

- colonne et total `EMPLOYES`;
- colonne et total `CLIENTS`;
- nom de l'employe, numero d'etiquette et departement pour une ligne employe;
- nom du client et numero de chambre pour une ligne client d'hotel.

`InvoicePresentationService` centralise la presentation de ces donnees pour eviter que la logique soit dupliquee entre l'ecran, le PDF et les exports.

### Taxes et argent

- tous les montants persistants sont des cents entiers;
- `MoneyFormatter` gere les formats francais et anglais;
- Quebec: TPS 5 % et TVQ 9,975 %;
- Ontario: taxe unique 13 % avec libelle configurable;
- les taxes sont calculees apres les rabais applicables;
- le drapeau taxable des categories est respecte;
- les taux et libelles sont instantanes dans chaque facture.

### PDF et CSV

- PDF Dompdf avec portrait ou paysage selon le contenu;
- presentation compacte pour les grands catalogues;
- facture detaillee et totaux EMPLOYES/CLIENTS;
- chemins PDF stockes dans la base;
- export CSV en dollars lisibles, pas en cents bruts;
- telechargements proteges par authentification et autorisation.

## Tables et migrations importantes

Les migrations doivent etre executees dans l'ordre normal avec `php artisan migrate --force`. Ne jamais utiliser `migrate:fresh`, `db:wipe` ou `app:clean-demo-data` sur la production.

Principales tables:

- `users`, `clients`, `client_categories`;
- `daily_records`, `daily_record_items`;
- `monthly_invoices`, `monthly_invoice_entries`;
- `monthly_invoice_daily_record`;
- `invoice_adjustments`, `payments`, `uploaded_documents`, `audit_logs`;
- `client_employee_names`, `cleaning_orders`, `cleaning_order_items`.

Migrations recentes a ne pas oublier lors d'un deploiement:

```text
2026_06_10_000001_add_client_ordering_tables.php
2026_06_14_000001_add_department_and_invoice_to_cleaning_orders.php
2026_07_28_000001_add_catalog_grouping_to_client_categories.php
2026_07_30_000001_add_hotel_order_identity_fields.php
```

La derniere ajoute `order_type`, `employee_tag_number`, `guest_name` et `room_number` aux commandes.

## Hebergement cPanel actuellement utilise

Dernier hebergement confirme par Marc-Aime: l'ancien environnement de demonstration demeure utilise.

- URL: `https://appvilleneuve.webactiondemo.ca`
- racine privee Laravel:
  `~/domains/appvilleneuve.webactiondemo.ca/app_core`
- racine publique:
  `~/domains/appvilleneuve.webactiondemo.ca/public_html`

Structure attendue:

```text
domains/appvilleneuve.webactiondemo.ca/
|-- app_core/
|   |-- app/
|   |-- bootstrap/
|   |-- config/
|   |-- database/
|   |-- resources/
|   |-- routes/
|   |-- storage/
|   |-- vendor/
|   |-- artisan
|   |-- composer.json
|   |-- composer.lock
|   `-- .env
`-- public_html/
    |-- index.php
    |-- .htaccess
    |-- build/
    |-- favicon.svg
    `-- storage -> ../app_core/storage/app/public
```

Seul le contenu de `public/` va dans `public_html`. Le dossier prive `app_core` ne doit jamais etre place dans `public_html`.

Le fichier `public_html/index.php` doit charger:

```php
require __DIR__.'/../app_core/vendor/autoload.php';
$app = require_once __DIR__.'/../app_core/bootstrap/app.php';
```

`bootstrap/app.php` et `config/dompdf.php` detectent actuellement un dossier frere `../public_html`. Ce comportement correspond a l'ancien domaine ci-dessus.

## Methode de mise a jour cPanel

### Avant tout televersement

1. Sauvegarder la base MySQL avec phpMyAdmin ou `mysqldump`.
2. Sauvegarder `app_core/.env` sans l'exposer.
3. Sauvegarder `app_core/storage/app/public`.
4. Noter les fichiers locaux modifies avec `git status` et `git diff --name-only`.
5. Construire et tester localement si le runtime est disponible.

### Destination des fichiers

| Fichiers locaux | Destination cPanel |
| --- | --- |
| `app/**` | `app_core/app/**` |
| `bootstrap/**` | `app_core/bootstrap/**` |
| `config/**` | `app_core/config/**` |
| `database/migrations/**` | `app_core/database/migrations/**` |
| `resources/**` | `app_core/resources/**` |
| `routes/**` | `app_core/routes/**` |
| `artisan`, `composer.json`, `composer.lock` | `app_core/` |
| `vendor/**` si Composer est indisponible sur le serveur | `app_core/vendor/**` |
| contenu de `public/**` | `public_html/**` |
| `public/build/**` produit par Vite | `public_html/build/**` |

Ne pas televerser dans `public_html`:

- `.env`;
- `app`, `bootstrap`, `config`, `database`, `resources`, `routes`;
- `vendor`;
- `storage/logs`;
- `composer.json` ou `composer.lock`;
- `node_modules`;
- une sauvegarde SQL.

Ne pas remplacer le `.env` de production par le `.env` local. Conserver aussi la meme `APP_KEY` lors d'une mise a jour ou migration de base, sinon les donnees chiffrees et sessions peuvent casser.

### Construction locale

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

Si `package-lock.json` est a jour, `npm ci` peut remplacer `npm install`.

### Commandes cPanel apres televersement

```bash
cd ~/domains/appvilleneuve.webactiondemo.ca/app_core
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Si les dependances PHP ont change et Composer est disponible:

```bash
composer install --no-dev --optimize-autoloader
```

Si le lien de stockage manque:

```bash
php artisan storage:link
```

Permissions habituelles sur cet hebergement:

```bash
chmod -R 775 storage bootstrap/cache
```

Utiliser les permissions les plus restrictives qui fonctionnent. Ne pas mettre tout le projet en `777`.

### Verification en direct apres mise a jour

Tester au minimum:

1. page publique et CSS/JS;
2. connexion super administrateur;
3. connexion employe;
4. connexion client et isolation entre deux clients;
5. creation d'une commande employe;
6. creation d'une commande client d'hotel;
7. impossibilite de modifier un prix cote client;
8. modification d'une commande soumise;
9. revision et conversion en facture;
10. facture mensuelle regroupant plusieurs commandes;
11. colonnes EMPLOYES et CLIENTS et renseignements associes;
12. taxes Ontario et Quebec;
13. PDF;
14. CSV;
15. televersement et telechargement d'une piece jointe;
16. filtres de statut;
17. suppression d'une facture test et liberation de ses commandes sources.

Consulter en cas d'erreur:

```bash
tail -n 100 storage/logs/laravel.log
php artisan route:list
php artisan migrate:status
```

## Projet de migration vers le nouveau domaine

Une migration vers `https://app.nettoyeur-villeneuve.ca` a ete discutee. Le sous-domaine devait pointer vers `public_html/app`, mais Marc-Aime a ensuite confirme rester sur l'ancien hebergement pour les derniers televersements.

La migration vers le nouveau domaine n'est donc pas consideree comme terminee ni verifiee. Le plan se trouve dans:

```text
deployment/cpanel/MIGRATION_HANDOFF.md
```

Ce nouveau chemin exigerait notamment:

- application privee dans `domains/nettoyeur-villeneuve.ca/app_core`;
- contenu public dans `domains/nettoyeur-villeneuve.ca/public_html/app`;
- `index.php` avec des chemins `../../app_core/...`;
- adaptation de la detection du chemin public dans `bootstrap/app.php` et `config/dompdf.php`;
- sauvegarde et migration de la base, du `.env` et de `storage/app/public`;
- verification complete avant de couper l'ancien domaine.

Ne pas supposer que cette migration a eu lieu. Inspecter cPanel et demander a l'utilisateur de saisir lui-meme les identifiants prives dans le navigateur autorise.

## Installation locale sur un nouvel ordinateur

1. Recuperer le dossier complet, y compris les changements non commits.
2. Ouvrir un terminal dans la racine du projet.
3. Verifier:

```bash
git status --short --branch
git diff --stat
```

4. Installer les dependances:

```bash
composer install
npm install
```

5. Creer `.env` local a partir de `.env.example`, configurer une base locale et generer une cle locale:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

6. Lancer:

```bash
php artisan serve
```

Ne jamais reutiliser le `.env` de production sur un ordinateur personnel.

## Verification automatisee

Commandes attendues:

```bash
php artisan test
php artisan test --filter=CleaningOrderInvoiceWorkflowTest
php artisan test --filter=InvoiceCatalogAdministrationTest
php artisan test --filter=MonthlyInvoiceApprovalWorkflowTest
npm run build
git diff --check
```

Au moment de ce handoff, PHP et Node ne sont pas disponibles directement dans le shell WSL actuel. Le dernier ensemble de changements non commits n'a donc pas ete revalide integralement dans ce shell. Des tests de fonctions ont ete ajoutes ou modifies, et Marc-Aime a effectue plusieurs tests manuels en production, mais le prochain Codex doit relancer la suite complete avant un commit ou un deploiement majeur.

Ne pas confondre une verification syntaxique, un build Vite ou un test manuel isole avec une preuve que toute la suite Laravel passe.

## Fichiers a lire en premier

```text
CODEX_HANDOFF.md
README.md
routes/web.php
bootstrap/app.php
config/filesystems.php
config/dompdf.php
config/shared_catalogs.php
deployment/cpanel/README.md
deployment/cpanel/MIGRATION_HANDOFF.md
app/Services/InvoiceCalculationService.php
app/Services/InvoiceApprovalService.php
app/Services/InvoicePresentationService.php
app/Services/SharedCatalogService.php
app/Http/Controllers/PortalOrderController.php
app/Http/Controllers/AdminCleaningOrderController.php
app/Http/Controllers/AccountStatementController.php
app/Http/Controllers/MonthlyInvoiceController.php
```

## Demarrage recommande pour le nouveau chat Codex

Premier message a envoyer dans le nouveau chat, depuis la racine du projet:

```text
Lis AGENTS.md et CODEX_HANDOFF.md en entier avant de faire quoi que ce soit.
Ensuite, execute git status --short --branch et git diff --stat. Ne supprime et
ne reinitialise aucun changement non commit. Resume-moi la difference entre
l'etat local, le dernier commit et l'etat de production connu. Verifie les
tests disponibles avant toute modification ou mise a jour cPanel. Ne demande
jamais de mot de passe dans le chat et ne remplace jamais le .env de production.
```

## Definition de fini pour toute future demande

Une demande n'est terminee que lorsque:

- le code et les migrations necessaires sont faits;
- les controles d'autorisation serveur sont presents;
- les calculs monetaires restent en cents;
- les tests pertinents passent ou l'impossibilite de les lancer est clairement indiquee;
- les fichiers a televerser sont listes avec leur destination exacte;
- les commandes post-deploiement sont donnees;
- la verification en direct est faite avant d'annoncer que la production fonctionne.
