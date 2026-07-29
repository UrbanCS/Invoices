<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\SharedCatalogService;
use Illuminate\Console\Command;

class ApplySharedCatalogs extends Command
{
    protected $signature = 'app:apply-shared-catalogs
        {--force : Appliquer les catalogues sans demander de confirmation}';

    protected $description = 'Copie le catalogue Holiday Inn aux hôtels désignés et applique le catalogue commerces Ottawa.';

    public function handle(SharedCatalogService $catalogs): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'Remplacer les catalogues actifs des hôtels et commerces configurés? Les anciennes factures resteront intactes.'
        )) {
            return self::SUCCESS;
        }

        $updatedClients = 0;
        $source = $catalogs->findClientByAliases(config('shared_catalogs.hotel_source_aliases', []));

        if (! $source) {
            $this->warn('Source Holiday Inn introuvable. Les catalogues des hôtels n’ont pas été modifiés.');
        } elseif ($source->categories()->where('is_active', true)->doesntExist()) {
            $this->warn("Le client source {$source->name} ne contient aucun item actif.");
        } else {
            $this->info("Source hôtels: {$source->name}");
            foreach (config('shared_catalogs.hotel_targets', []) as $label => $aliases) {
                $target = $catalogs->findClientByAliases($aliases);
                if (! $target) {
                    $this->warn("Hôtel introuvable: {$label}");
                    continue;
                }

                if ($target->is($source)) {
                    $this->warn("Cible ignorée car elle correspond à la source: {$target->name}");
                    continue;
                }

                $count = $catalogs->copyActiveCatalog($source, $target);
                $updatedClients++;
                $this->line("  - {$target->name}: {$count} item(s)");
            }
        }

        $this->info('Application du catalogue commerces Ottawa');
        foreach (config('shared_catalogs.store_targets', []) as $label => $aliases) {
            $target = $catalogs->findClientByAliases($aliases);
            if (! $target) {
                $this->warn("Commerce introuvable: {$label}");
                continue;
            }

            $count = $catalogs->applyStoreOttawaCatalog($target);
            $updatedClients++;
            $this->line("  - {$target->name}: {$count} item(s)");
        }

        if ($updatedClients === 0) {
            $this->error('Aucun client correspondant n’a été trouvé. Vérifie les noms dans la liste des clients.');

            return self::FAILURE;
        }

        $this->info("Catalogues appliqués à {$updatedClients} client(s). Les profils de taxes ont été conservés.");

        return self::SUCCESS;
    }
}
