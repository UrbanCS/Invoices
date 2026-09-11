# Handoff Codex - Nettoyeur Villeneuve

Derniere mise a jour: 2026-09-11

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

- Windows: `C:\Users\marca\OneDrive\Documents\Invoices\Invoices`
- WSL: `/mnt/c/Users/marca/OneDrive/Documents/Invoices/Invoices`
- Depot Git: la copie locale verifiee le 2026-08-31 ne contient pas de dossier `.git`; `git status` et `git diff` ne sont donc pas disponibles dans cette copie. Les mentions historiques de la branche `main` ci-dessous doivent etre confirmees depuis une copie qui contient les metadonnees Git.
- Application: Laravel 11, PHP 8.2+, MySQL/MariaDB
- Interface: Blade, Tailwind CSS, Alpine.js et Vite
- PDF: `barryvdh/laravel-dompdf`
- Production: PHP et MySQL seulement; aucun serveur Node, Redis, Docker ou worker permanent

## Synchronisation Git du 2026-09-11

- reprise depuis `UrbanCS/Invoices`, branche `main`, commit de depart `99d3b13dc907587ca64b460fdf409663ad24ab8c`;
- integration des changements livres du 31 aout au 10 septembre, avec les tests et traductions correspondants;
- comparaison effectuee dans un clone temporaire distinct; la copie OneDrive sans `.git` est conservee;
- aucun fichier `.env`, base de donnees, PDF client, cache, dependance installee ou archive de sauvegarde ajoute au depot;
- les details financiers des verifications de production sont omis de cette version publique du handoff;
- derniere suite complete executee le 10 septembre: 51 tests, 415 assertions; runtime PHP temporaire absent le 11 septembre, donc suite non relancee pour cette synchronisation;
- aucun nouveau deploiement du site requis pour cette operation Git.

## Etat verifie et deploiement du 2026-09-10

Demande livree:

- ajout de `Sac / Bag` a 0 cent, actif et non taxable, dans les audiences `employees` et `unisex` des dix hotels actifs;
- commande idempotente `php artisan app:apply-hotel-bag --dry-run` puis `--force`; fusion ciblee sans remplacement des autres articles ni de leurs prix;
- ciblage des clients actifs de style hotel ou des alias explicites de configuration; Hilton Lac Leamy est inclus pour cet article gratuit, sans changer l'exclusion de son catalogue EMPLOYES regulier;
- preservation des articles gratuits dans le calculateur manuel et a l'enregistrement/modification; les cases vides et les lignes a zero de categories payantes ne deviennent pas des entrees;
- un seul total en cents entiers par groupe jour/type/personne/etiquette/chambre/departement, en HTML et PDF; les autres identites restent separees;
- suppression des mentions service/audience sous les items factures; les regroupements restent disponibles dans le selecteur de catalogue;
- totaux et ajustements (rabais, credits, frais) apres le detail des items; libelles PDF francais et anglais;
- PDF avec cellules d'identite/total fusionnees et lignes d'articles alignees; repartition mensuelle compactee pour eviter une page supplementaire presque vide;
- aucune migration ni dependance ajoutee.

Verification locale:

- absence de `.git` encore confirmee par git status et git diff; aucun commit/push ni annulation de changement;
- sauvegarde des fichiers originaux dans `C:\Users\marca\AppData\Local\Temp\codex-bags-20260910-before`;
- suite complete: 51 tests, 415 assertions, succes; six nouveaux tests dans `HotelBagAndInvoiceLayoutTest` couvrent le deploiement idempotent, les articles gratuits seuls et mixtes, creation/modification, le portail, les tarifs serveur, les audiences et la conversion commande-facture, les totaux groupes et les ajustements FR/EN;
- tests PDF existants adaptes aux nouveaux totaux groupes et a la suppression des sous-libelles;
- compilation Blade et rendus Dompdf locaux FR/EN reussis; inspection visuelle avec Poppler;
- comparaison SHA-256 des huit fichiers existants avant deploiement: correspondance exacte local/serveur.

Deploiement via le navigateur interne DirectAdmin:

- sauvegarde complete annoncee prete le 2026-09-10 a 14 h 33 selon DirectAdmin;
- sauvegarde ciblee privee `app_core/hotel-bags-backup-20260910-before-deploy.tar.gz`;
- archive `app_core/hotel-bags-20260910.zip`, SHA-256 `dfffb4518d91b08e2f5b63db03c5563dc8b061bdd8ef27c221b724d80dea2ec2`, televersee avec le gestionnaire de fichiers, testee et extraite au terminal;
- neuf fichiers deployes: `app/Services/InvoicePresentationService.php`, `app/Services/SharedCatalogService.php`, `app/Http/Controllers/MonthlyInvoiceController.php`, `app/Console/Commands/ApplyHotelBag.php`, `resources/views/monthly-invoices/form.blade.php`, `resources/views/monthly-invoices/show.blade.php`, `resources/views/pdf/monthly-invoice.blade.php`, `lang/fr/invoice.php`, `lang/en/invoice.php`;
- lint PHP, optimize:clear, config:cache, route:cache et view:cache: succes;
- dry-run verifie puis application aux hotels IDs 12, 15, 16, 17, 18, 19, 21, 22, 28, 29; resultat serveur `BAGS_OK hotels=10` confirme les deux audiences actives au prix zero pour chaque hotel;
- facture reelle a plusieurs items verifiee: total unique par groupe et grand total inchange; sous-libelles retires;
- PDF de cette facture regenere avec le bouton du site, telecharge, rendu et inspecte sur ses deux pages;
- test navigateur sans enregistrement: employe avec deux sacs gratuits, puis client hotel avec un sac gratuit et deux suits a 2290 cents; les trois entrees du formulaire sont presentes et le total demeure 4580 cents; formulaire ensuite abandonne, aucune erreur console;
- test serveur de syncGrid avec deux entrees gratuites employe/client dans une transaction annulee: `FREE_SAVE_OK employee+guest`, `ROLLBACK_OK no test entries retained`;
- rendus serveur du template avec ajustements temporaires en memoire: `BOTTOM_ADJUSTMENTS_OK fr` et `en`; aucune modification des ajustements ou de la langue client en base;
- aucune facture/commande de test conservee; seule la mise a jour des catalogues et la regeneration du PDF reel sont persistantes;
- archives de sauvegarde/deploiement conservees, ne pas les supprimer sans autorisation.

Important: un PDF deja genere conserve son ancienne presentation tant que le bouton `Generer PDF` n'est pas utilise. Aucun remplacement en masse des anciens PDF n'a ete effectue. Pour de futurs hotels, relancer la commande de sacs apres verification du dry-run; leur creation ne declenche pas automatiquement cette commande.

## Etat verifie et deploiement du 2026-09-07

Probleme signale dans `Etat-de-compte-facturedumois.mp4`:

- les factures quotidiennes d'Arc The Hotel Ottawa etaient visibles dans Factures mais absentes des Etats de compte;
- le controleur des etats de compte ne chargeait que `CleaningOrder`, et le bouton de facture mensuelle ne traitait que les commandes approuvees non facturees.

Correctif:

- ajout de la section `Factures du mois` aux etats de compte, filtree par client et periode de facturation (`invoice_month` / `invoice_year`), y compris si la date d'emission est ulterieure;
- inclusion des factures approuvees, envoyees ou payees, exclusion des brouillons et annulations;
- recapitulatif HTML et PDF unique des factures existantes, avec numeros, dates, statuts, montants avant taxes, taxes, paiements enregistres et solde;
- sommes reprises des factures en cents entiers, sans recalculer leurs taxes avec le profil actuel du client;
- le recapitulatif est un etat de compte et ne cree aucune nouvelle facture ni creance; sa regeneration ne duplique pas les revenus;
- PDF en francais ou anglais selon la langue du client; aucun ajout du bloc de remerciement/cheques retire precedemment;
- parcours distinct conserve pour les commandes non facturees, avec un libelle explicite;
- nouvel acces GET authentifie `account-statements.summary`, reserve aux super administrateurs et employes;
- aucune migration ni changement de dependance applicative.

Validation:

- suite PHPUnit complete: **45 tests, 341 assertions**, succes;
- six nouveaux tests couvrent le cas des factures sans commandes, la periode/client/statut, les paiements, les acces, les parametres invalides et la regeneration PDF sans mutation;
- apres mise en forme et ajustement de pagination: tests cibles 6/46 et compilation Blade reussis;
- vrais PDF locaux francais (3 factures) et anglais (50 factures) rendus avec Dompdf et inspectes visuellement avec Poppler;
- les trois fichiers remplaces en production ont ete compares a la copie locale de depart par SHA-256: correspondance exacte;
- absence de `.git` confirmee: aucune annulation de changement local et aucun commit/push effectue.

Deploiement via le navigateur interne et le terminal DirectAdmin:

- sauvegarde complete annoncee prete le 2026-09-07 a 22 h 41, selon l'interface DirectAdmin;
- sauvegarde ciblee `app_core/monthly-statement-backup-20260907-before-deploy.tar.gz`;
- archive `app_core/monthly-statement-20260907.zip`, SHA-256 `975936158bd2045af66dfdbde2ea57d0cdc1ffcc8abf546b3cb25e9f93facbc8`;
- sept fichiers applicatifs deployes dans `app_core`: `app/Services/AccountStatementService.php`, `app/Http/Controllers/AccountStatementController.php`, `resources/views/account-statements/index.blade.php`, `resources/views/pdf/account-statement.blade.php`, `routes/web.php`, `lang/fr/statement.php`, `lang/en/statement.php`;
- lint PHP et `optimize:clear`, `config:cache`, `route:cache`, `view:cache`: succes;
- test web authentifie sur un client avec plusieurs factures: montants avant taxes, taxes, total et solde verifies;
- PDF telecharge avec le bouton du site, ouvert et inspecte: trois factures et montants corrects, document lisible sur une page;
- controle croise sur un autre client et une autre periode: isolation et absence de reprise des factures d'un autre mois confirmees;
- aucune erreur console relevee; aucune facture, commande ou donnee de test creee en production;
- archives de sauvegarde et de deploiement conservees sur le serveur.

Utilisation cliente: Etats de compte > choisir mois, annee et client > Filtrer > Telecharger le recapitulatif du mois (PDF).

## Etat verifie et deploiement du 2026-09-01

Demande livree:

- memorisation des noms d'employes saisis dans la creation ou la modification manuelle d'une facture;
- reutilisation du catalogue existant `client_employee_names`, deja alimente par les commandes du portail;
- suggestions limitees au client ou a l'hotel selectionne, sans fuite entre hotels;
- saisie libre conservee: un nouveau nom peut toujours etre entre et il est memorise seulement apres l'enregistrement reussi de la facture;
- seules les lignes de type `employee` alimentent cette liste; les noms de clients d'hotel n'y sont pas ajoutes;
- aucune migration requise, car la table et les relations existaient deja.

Verification locale:

- suite PHPUnit complete: 39 tests, 295 assertions, succes;
- test cible ajoutant un employe a une facture manuelle, confirmant sa memorisation pour l'hotel choisi, son affichage au prochain formulaire et son absence pour un autre hotel: succes;
- `php artisan view:cache`: succes;
- lint PHP du controleur et du test modifies: succes;
- verification syntaxique du JavaScript integre au formulaire: succes;
- le dossier de travail principal ne contient toujours pas de metadonnees `.git`; aucun changement existant n'a ete supprime ou reinitialise.

Production confirmee sur `https://appvilleneuve.webactiondemo.ca`:

- sauvegarde complete DirectAdmin terminee le 2026-09-01 a 11 h 58 avant le deploiement;
- sauvegarde ciblee de retour arriere creee dans `app_core`: `employee-name-memory-backup-20260901-before-deploy.tar.gz`;
- archive `employee-name-memory-20260901.zip` verifiee par SHA-256 puis extraite dans `app_core`; elle contient uniquement le controleur et le formulaire de facture mensuelle prevus;
- migration existante `2026_06_10_000001_add_client_ordering_tables` confirmee executee; aucune nouvelle migration lancee;
- lint PHP du controleur deploye: succes;
- `php artisan optimize:clear`, `config:cache`, `route:cache` et `view:cache` executes avec succes;
- test serveur du nouveau mecanisme avec une facture existante dans une transaction annulee: `EMPLOYEE_MEMORY_OK`; aucune donnee de test n'a ete conservee;
- verification authentifiee de `/monthly-invoices/create?client_id=15`: les sept noms deja memorises pour Metcalfe Hotel sont charges dans la liste de suggestions et le texte d'aide est visible;
- verification croisee avec Lord Elgin Hotel (`client_id=19`): aucune suggestion de Metcalfe n'est presente, ce qui confirme l'isolation par hotel;
- test JavaScript sans enregistrement: un nom temporaire et un numero d'etiquette ont ete ajoutes au resume et a la liste de suggestions de la page, puis la navigation a abandonne le formulaire; le nom temporaire n'etait plus present au rechargement et aucune facture n'a ete soumise;
- aucune erreur JavaScript n'a ete relevee dans la console du navigateur pendant la verification;
- les archives de deploiement et de retour arriere du 2026-09-01 sont conservees sur le serveur et ne doivent pas etre supprimees sans autorisation explicite.

## Etat verifie et deploiement du 2026-08-31

Demande livree:

- regroupement, dans le detail HTML et PDF d'une facture, des items d'une meme personne, d'une meme journee et d'une meme identite sur une seule rangee;
- conservation de rangees distinctes lorsque la personne ou sa reference differe;
- conservation du titre original `Detail des items factures`;
- suppression complete du bloc de pied de page contenant le remerciement, les instructions pour les cheques et le nom `Nettoyeur Villeneuve`;
- ajout de l'anglais dans le choix de langue d'un client;
- localisation des libelles, statuts, references, services et montants du PDF selon la langue du client.

Verification locale:

- suite PHPUnit complete: 38 tests, 285 assertions, succes;
- `php artisan view:cache`: succes;
- lint PHP de tous les fichiers PHP modifies: succes;
- rendus Dompdf reels en francais et en anglais: succes; inspection visuelle du PDF anglais sans pied de page;
- le cas Bell, jour 31, avec `Trouser` x1 et `Shirts` x2 est presente sur une seule rangee; Alesso reste sur une rangee distincte;
- comparaison effectuee contre une copie temporaire propre de `main` au commit `99d3b13`: 10 fichiers modifies ou ajoutes, 428 insertions et 89 suppressions; `git diff --check` sans erreur;
- le dossier de travail principal ne contient toujours pas de metadonnees `.git`; aucun changement existant n'a ete supprime ou reinitialise.

Production confirmee sur `https://appvilleneuve.webactiondemo.ca`:

- sauvegarde complete DirectAdmin terminee le 2026-08-31 a 16 h 50 avant le deploiement;
- sauvegarde ciblee de retour arriere creee dans `app_core`: `invoice-pdf-localization-backup-20260831-before-deploy.tar.gz`;
- archive de deploiement `invoice-pdf-localization-20260831.zip` televersee et extraite dans `app_core`; elle contient uniquement les huit fichiers applicatifs et de traduction prevus;
- lint PHP en production des controleurs, services et fichiers de traduction: succes;
- `php artisan optimize:clear`, `config:cache`, `route:cache` et `view:cache` executes avec succes;
- verification authentifiee de la facture 0826: le titre original `Detail des items factures` est affiche; Bell et ses deux items du jour 31 sont regroupes sur une seule rangee, tandis qu'Alesso est separe;
- verification authentifiee de la fiche Lord Elgin Hotel: le choix de langue propose `Francais` et `Anglais`, avec l'explication que ce choix pilote les libelles et les montants du PDF;
- correctif d'interpretation redeploye avec `invoice-footer-correction-20260831.zip` apres creation de `invoice-footer-correction-backup-20260831-before-deploy.tar.gz`;
- rendus PDF francais et anglais executes en memoire sur le serveur avec les donnees de la facture 0826, sans modifier la base ni le fichier PDF existant: titre original francais, titre anglais, absence du bloc de pied de page et sortie `%PDF-` valides (`FOOTER_CORRECTION_OK`);
- aucune migration n'etait requise et aucune donnee de production n'a ete modifiee pendant les tests;
- les archives de deploiement et de retour arriere du 2026-08-31 ont ete conservees sur le serveur; elles ne doivent pas etre supprimees sans autorisation explicite.

## Etat verifie et deploiement du 2026-08-27

Demande livree:

- ajout du champ `No d'etiquette` pour les commandes de clients d'hotel, distinct du numero de chambre;
- ajout du bouton `Ajouter item` dans la creation manuelle d'une facture afin de preparer plusieurs items pour une meme journee avant de les ajouter ensemble a la facture;
- ajout d'un second bouton `Ajouter a la facture` sous les derniers champs d'identite;
- compatibilite conservee avec les anciennes donnees hotel qui utilisaient `reference_number` comme numero de chambre;
- presentation mise a jour dans le portail, les etats de compte, les factures, les PDF et le CSV.

Verification locale:

- suite PHPUnit complete: 36 tests, 260 assertions, succes;
- `php artisan view:cache`: succes;
- lint PHP des fichiers modifies: succes;
- verification syntaxique du JavaScript integre: succes;
- `npm ci` et build Vite dans un dossier temporaire: succes;
- `npm audit` a signale 5 vulnerabilites de dependances de severite elevee; aucune correction automatique hors mandat n'a ete appliquee;
- aucun changement local n'a ete supprime ou reinitialise;
- cette copie n'a pas de metadonnees Git, donc aucun statut, diff ou commit Git fiable ne peut etre produit depuis ce dossier.

Production confirmee sur `https://appvilleneuve.webactiondemo.ca`:

- sauvegarde complete DirectAdmin terminee avant le deploiement le 2026-08-27;
- 16 fichiers applicatifs cibles televerses et extraits dans `app_core` avec fusion et remplacement;
- migration `2026_08_27_000001_add_guest_tag_number_to_cleaning_orders.php` executee avec succes;
- `php artisan optimize:clear`, `config:cache`, `route:cache` et `view:cache` executes avec succes;
- les permissions invalides preexistantes de `app/Http` et `resources/views` bloquaient l'acces aux controleurs et aux vues; les dossiers concernes ont ete remis a `755` avant de reconstruire les caches;
- page publique chargee avec le titre `Nettoyeur Villeneuve` et la route protegee `/monthly-invoices/create` redirige correctement vers `/login` lorsque la session est fermee;
- verification visuelle authentifiee de `/monthly-invoices/create`: le bouton `Ajouter item`, les deux boutons `Ajouter a la facture`, le champ hotel `No d'etiquette` et le champ separe `No de chambre` sont presents et actifs dans le bon contexte;
- test manuel sans enregistrement: jour 26, `Trouser` x2, `Shirts` x3 et `Dress (and up)` x1 ont ete prepares ensemble; la liste temporaire affichait les trois lignes et verrouillait le jour/type. La page a ensuite ete rechargee pour abandonner le test sans creer de facture ni modifier la base;
- correctif du bug signale dans `Bug.mp4`: l'expression `firstTarget ??= appendItemToInvoice(...)` court-circuitait les appels apres le premier item. L'ajout de chaque item est maintenant execute avant de memoriser la premiere cible;
- correctif televerse dans `resources/views/monthly-invoices/form.blade.php` apres creation de la sauvegarde serveur `form.blade.php.bak-20260827-multiitem`, puis `php artisan view:cache` execute avec succes;
- test authentifie en production sans enregistrement: jour 22, `Jacket` x4 et `Shirts` x7 sont tous les deux presents dans le resume final, avec un total de 44,50 $. La page a ete rechargee et aucune facture de test n'a ete creee;
- l'archive `nettoyeur-villeneuve-20260827-3d255abbce5c4a698baf2b530d6d1d99.zip` a ete laissee dans `app_core`; elle n'a pas ete supprimee sans autorisation explicite.

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
4. Pour un client de l'hotel, il indique le nom du client, le numero d'etiquette et le numero de chambre.
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
2026_08_27_000001_add_guest_tag_number_to_cleaning_orders.php
```

La migration du 2026-07-30 ajoute `order_type`, `employee_tag_number`, `guest_name` et `room_number` aux commandes. Celle du 2026-08-27 ajoute `guest_tag_number` afin de conserver separement le numero d'etiquette et le numero de chambre d'un client d'hotel.

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

Le 2026-08-27, PHP 8.4.24 et Composer 2.10.3 ont ete installes uniquement dans des dossiers temporaires locaux pour valider le projet. Apres le correctif multi-item, la suite complete a reussi avec 36 tests et 260 assertions. Node a aussi permis `npm ci`, la verification syntaxique du JavaScript integre et un build Vite dans un dossier temporaire. Ces runtimes temporaires ne doivent pas etre supposes disponibles dans un prochain shell.

Le 2026-08-31, apres le regroupement des lignes de facture, la localisation anglaise et la suppression du pied de page demande, la suite complete a reussi avec 38 tests et 285 assertions. Un vrai PDF anglais sans ce bloc a ete genere localement, rendu en image avec Poppler et inspecte visuellement. Les rendus francais et anglais ont ensuite ete executes en memoire sur la production avec Dompdf, sans ecriture en base ni remplacement du PDF existant.

Le 2026-09-01, apres l'ajout de la memorisation des noms d'employes dans les factures manuelles, la suite complete a reussi avec 39 tests et 295 assertions. Le test cible confirme la memorisation apres enregistrement, les suggestions au prochain formulaire, l'isolation par hotel et la saisie libre. La production a aussi retourne `EMPLOYEE_MEMORY_OK` lors d'un essai dans une transaction annulee.

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
