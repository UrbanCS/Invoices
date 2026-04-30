<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyInvoice extends Model
{
    protected $fillable = [
        'client_id', 'invoice_number', 'invoice_month', 'invoice_year', 'invoice_date', 'status',
        'source_mode', 'subtotal_cents', 'discount_cents', 'taxable_subtotal_cents', 'tax_cents',
        'gst_cents', 'qst_cents', 'total_cents', 'grand_total_cents', 'tax_profile_snapshot',
        'category_snapshot', 'notes', 'payment_instructions', 'thank_you_message', 'pdf_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'tax_profile_snapshot' => 'array',
            'category_snapshot' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MonthlyInvoiceEntry::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InvoiceAdjustment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function dailyRecords(): BelongsToMany
    {
        return $this->belongsToMany(DailyRecord::class, 'monthly_invoice_daily_record')->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UploadedDocument::class);
    }
}
