<?php

namespace App\Console\Commands;

use App\Services\SharedCatalogService;
use Illuminate\Console\Command;

class ApplyEmployeeCatalog extends Command
{
    protected $signature = 'app:apply-employee-catalog
        {--force : Appliquer le catalogue sans demander de confirmation}';

    protected $description = 'Ajoute la section EMPLOYÉS aux hôtels configurés, sauf Hilton Lac-Leamy.';

    public function handle(SharedCatalogService $catalogs): int
    {
        $targets = $catalogs->employeeHotelClients();

        if ($targets->isEmpty()) {
            $this->error('Aucun hôtel admissible trouvé. Vérifie les noms dans config/shared_catalogs.php.');

            return self::FAILURE;
        }

        $this->table(
            ['Hôtels ciblés'],
            $targets->map(fn ($client) => [$client->name])->all(),
        );

        if (! $this->option('force') && ! $this->confirm(
            'Ajouter ou mettre à jour les 8 tarifs EMPLOYÉS? Les autres items seront conservés.'
        )) {
            return self::SUCCESS;
        }

        foreach ($targets as $target) {
            $count = $catalogs->applyEmployeeCatalog($target);
            $this->line("  - {$target->name}: {$count} item(s) EMPLOYÉS");
        }

        $this->info("Catalogue EMPLOYÉS appliqué à {$targets->count()} hôtel(s). Hilton Lac-Leamy est exclu.");

        return self::SUCCESS;
    }
}
