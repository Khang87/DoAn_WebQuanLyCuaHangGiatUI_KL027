<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'code',
        'invoice_date',
        'total',
        'total_amount',
        'discount_amount',
        'delivery_fee',
        'grand_total',
        'status',
        'notes',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'invoice_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'paid')->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->grand_total - $this->total_paid);
    }

    public function getStatusLabel(): string
    {
        return InvoiceStatus::labelFor($this->status);
    }

    public function getStatusBadgeClass(): string
    {
        return InvoiceStatus::badgeClassFor($this->status);
    }

    public function getStatusIcon(): string
    {
        return InvoiceStatus::iconFor($this->status);
    }

    /**
     * Hóa đơn đã quyết toán (đã thanh toán) thì số tiền không đổi được nữa.
     */
    public function isPaid(): bool
    {
        return InvoiceStatus::valueIsPaid($this->status);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->isPaid();
    }
}
