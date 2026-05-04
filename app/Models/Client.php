<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'billing_address', 'city', 'province', 'postal_code', 'phone',
        'email', 'logo_path', 'tax_profile', 'default_language', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ClientCategory::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeCategories(): HasMany
    {
        return $this->categories()->where('is_active', true);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(MonthlyInvoice::class);
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
