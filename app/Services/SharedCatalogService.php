<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SharedCatalogService
{
    public function copyActiveCatalog(Client $source, Client $target): int
    {
        $items = $source->categories()
            ->where('is_active', true)
            ->get()
            ->map(fn (ClientCategory $category) => [
                'service_type' => $category->service_type,
                'audience' => $category->audience,
                'name' => $category->name,
                'default_price_cents' => $category->default_price_cents,
                'sort_order' => $category->sort_order,
                'is_taxable' => $category->is_taxable,
            ]);

        return $this->replaceActiveCatalog($target, $items);
    }

    public function applyStoreOttawaCatalog(Client $target): int
    {
        $positions = [];
        $items = collect(config('shared_catalogs.store_ottawa', []))
            ->map(function (array $row) use (&$positions) {
                [$serviceType, $audience, $name, $priceCents] = $row;
                $group = $serviceType.'|'.$audience;
                $positions[$group] = ($positions[$group] ?? 0) + 1;

                return [
                    'service_type' => $serviceType,
                    'audience' => $audience,
                    'name' => $name,
                    'default_price_cents' => $priceCents,
                    'sort_order' => $positions[$group],
                    'is_taxable' => true,
                ];
            });

        return $this->replaceActiveCatalog($target, $items);
    }

    public function replaceActiveCatalog(Client $target, Collection $items): int
    {
        return DB::transaction(function () use ($target, $items) {
            $existing = $target->categories()->get();
            $existingByKey = $existing->keyBy(fn (ClientCategory $category) => $this->key(
                $category->service_type,
                $category->audience,
                $category->name,
            ));

            ClientCategory::where('client_id', $target->id)->update(['is_active' => false]);
            $activeIds = [];

            foreach ($items as $item) {
                $key = $this->key($item['service_type'], $item['audience'], $item['name']);
                $category = $existingByKey->get($key);
                $payload = [
                    'name' => $item['name'],
                    'service_type' => $item['service_type'],
                    'audience' => $item['audience'],
                    'sort_order' => max(0, (int) ($item['sort_order'] ?? 0)),
                    'is_taxable' => (bool) ($item['is_taxable'] ?? true),
                    'default_price_cents' => max(0, (int) ($item['default_price_cents'] ?? 0)),
                    'is_active' => true,
                ];

                if ($category) {
                    $category->update($payload);
                } else {
                    $category = $target->categories()->create($payload);
                    $existingByKey->put($key, $category);
                }

                $activeIds[] = $category->id;
            }

            return count($activeIds);
        });
    }

    public function findClientByAliases(array $aliases): ?Client
    {
        $normalizedAliases = collect($aliases)
            ->map(fn (string $alias) => $this->normalize($alias))
            ->filter()
            ->sortByDesc(fn (string $alias) => mb_strlen($alias));

        return Client::where('is_active', true)
            ->get()
            ->first(function (Client $client) use ($normalizedAliases) {
                $name = $this->normalize($client->name);

                return $normalizedAliases->contains(
                    fn (string $alias) => $name === $alias
                        || str_contains($name, $alias)
                        || str_contains($alias, $name),
                );
            });
    }

    private function key(string $serviceType, string $audience, string $name): string
    {
        return $serviceType.'|'.$audience.'|'.$this->normalize($name);
    }

    private function normalize(string $value): string
    {
        return (string) Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }
}
