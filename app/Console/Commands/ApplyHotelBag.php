<?php

namespace App\Console\Commands;

use App\Services\SharedCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyHotelBag extends Command
{
    protected $signature = 'app:apply-hotel-bag {--dry-run : Afficher les hôtels sans modifier les données} {--force : Appliquer sans confirmation}';

    protected $description = 'Ajoute Sac / Bag gratuit aux catalogues employés et clients de tous les hôtels actifs.';

    public function handle(SharedCatalogService $catalogs): int
    {
        $targets = $catalogs->hotelBagClients();
        $this->table(['ID', 'Hôtel'], $targets->map(fn ($client) => [$client->id, $client->name])->all());

        if ($targets->isEmpty()) {
            $this->error('Aucun hôtel trouvé.');

            return self::FAILURE;
        }

        if ($this->option('dry-run') || (! $this->option('force') && ! $this->confirm('Ajouter Sac / Bag à 0 $ aux deux catalogues? Les autres articles seront conservés.'))) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($targets, $catalogs) {
            foreach ($targets as $target) {
                $catalogs->applyHotelBag($target);
            }
        });
        $this->info("Sac / Bag appliqué à {$targets->count()} hôtel(s), pour les employés et les clients.");

        return self::SUCCESS;
    }
}
