<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CleaningOrder extends Model
{
    protected $fillable = [
        'client_id',
        'user_id',
        'monthly_invoice_id',
        'service_date',
        'employee_name',
        'department_number',
        'status',
        'subtotal_cents',
        'adjustment_cents',
        'adjustment_note',
        'total_cents',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'subtotal_cents' => 'integer',
            'adjustment_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MonthlyInvoice::class, 'monthly_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CleaningOrderItem::class);
    }
}
