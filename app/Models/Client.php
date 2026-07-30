<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'billing_address', 'city', 'province', 'postal_code', 'phone',
        'email', 'logo_path', 'tax_profile', 'default_language', 'invoice_style', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ClientCategory::class)
            ->orderByRaw("CASE service_type
                WHEN 'dry_cleaning' THEN 1
                WHEN 'laundry' THEN 2
                WHEN 'pressing' THEN 3
                ELSE 4
            END")
            ->orderByRaw("CASE audience
                WHEN 'gentlemen' THEN 1
                WHEN 'ladies' THEN 2
                WHEN 'employees' THEN 3
                WHEN 'unisex' THEN 4
                ELSE 5
            END")
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function activeCategories(): HasMany
    {
        return $this->categories()->where('is_active', true);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MonthlyInvoice::class);
    }

    public function cleaningOrders(): HasMany
    {
        return $this->hasMany(CleaningOrder::class);
    }

    public function employeeNames(): HasMany
    {
        return $this->hasMany(ClientEmployeeName::class)->orderBy('name');
    }

    public function dailyRecords(): HasMany
    {
        return $this->hasMany(DailyRecord::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UploadedDocument::class);
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class)->orderBy('sort_order');
    }
}
