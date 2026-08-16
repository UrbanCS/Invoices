<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CleaningOrder extends Model
{
    public const ORDER_TYPES = [
        'employee' => 'Employé',
        'hotel_guest' => 'Client de l’hôtel',
    ];

    protected $fillable = [
        'client_id',
        'user_id',
        'monthly_invoice_id',
        'service_date',
        'order_type',
        'employee_name',
        'employee_tag_number',
        'department_number',
        'guest_name',
        'room_number',
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

    public function isEmployeeOrder(): bool
    {
        return ($this->order_type ?: 'employee') === 'employee';
    }

    public function orderTypeLabel(): string
    {
        return self::ORDER_TYPES[$this->order_type ?: 'employee'] ?? self::ORDER_TYPES['employee'];
    }

    public function billingName(): ?string
    {
        return $this->isEmployeeOrder() ? $this->employee_name : $this->guest_name;
    }

    public function billingReference(): ?string
    {
        return $this->isEmployeeOrder() ? $this->employee_tag_number : $this->room_number;
    }

    public function billingReferenceLabel(): string
    {
        return $this->isEmployeeOrder() ? 'No d’étiquette' : 'No de chambre';
    }

    public function invoiceIdentitySnapshot(): array
    {
        return [
            'billing_type' => $this->isEmployeeOrder() ? 'employee' : 'hotel_guest',
            'person_name' => $this->billingName(),
            'reference_number' => $this->billingReference(),
            'department_number' => $this->isEmployeeOrder() ? $this->department_number : null,
        ];
    }
}
