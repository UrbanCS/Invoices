<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyInvoiceEntry extends Model
{
    protected $fillable = [
        'monthly_invoice_id', 'service_day', 'client_category_id', 'category_name_snapshot',
        'amount_cents', 'item_details', 'source_type',
    ];

    protected function casts(): array
    {
        return [
            'item_details' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class, 'monthly_invoice_id');
    }
}
