<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyRecord extends Model
{
    protected $fillable = [
        'client_id',
        'service_date',
        'status',
        'reference_number',
        'received_by',
        'hotel_signature',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['service_date' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DailyRecordItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UploadedDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalCents(): int
    {
        return (int) $this->items->sum('amount_cents');
    }
}
