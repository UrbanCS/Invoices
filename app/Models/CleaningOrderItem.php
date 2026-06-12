<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningOrderItem extends Model
{
    protected $fillable = [
        'cleaning_order_id',
        'client_category_id',
        'item_name_snapshot',
        'unit_price_cents',
        'quantity',
        'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity' => 'decimal:2',
            'total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CleaningOrder::class, 'cleaning_order_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category_id');
    }
}
