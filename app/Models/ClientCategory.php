<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCategory extends Model
{
    public const SERVICE_TYPES = [
        'dry_cleaning' => 'Nettoyage à sec / Dry Cleaning',
        'laundry' => 'Blanchissage / Laundry',
        'pressing' => 'Repassage / Pressing',
        'other' => 'Autres / Other',
    ];

    public const AUDIENCES = [
        'gentlemen' => 'Messieurs / Gentlemen',
        'ladies' => 'Dames / Ladies',
        'employees' => 'EMPLOYÉS',
        'unisex' => 'Tous / Unisex',
    ];

    protected $fillable = [
        'client_id',
        'name',
        'service_type',
        'audience',
        'sort_order',
        'is_taxable',
        'default_price_cents',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_taxable' => 'boolean', 'default_price_cents' => 'integer', 'is_active' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function serviceLabel(?string $serviceType): string
    {
        return self::SERVICE_TYPES[$serviceType ?? 'other'] ?? self::SERVICE_TYPES['other'];
    }

    public static function audienceLabel(?string $audience): string
    {
        return self::AUDIENCES[$audience ?? 'unisex'] ?? self::AUDIENCES['unisex'];
    }
}
