<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCategory extends Model
{
    protected $fillable = ['client_id', 'name', 'sort_order', 'is_taxable', 'is_active'];

    protected function casts(): array
    {
        return ['is_taxable' => 'boolean', 'is_active' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
