<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'status',
        'transaction_code',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getMethodLabel(): string
    {
        return match ($this->method) {
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản / QR',
            'momo' => 'Ví MoMo',
            'credit_card' => 'Thẻ ATM / Credit',
            'e_wallet' => 'Ví điện tử',
            default => $this->method,
        };
    }

    public function getMethodIcon(): string
    {
        return match ($this->method) {
            'cash' => 'bi-cash-coin',
            'bank_transfer' => 'bi-bank',
            'momo' => 'bi-phone',
            'credit_card' => 'bi-credit-card',
            'e_wallet' => 'bi-wallet2',
            default => 'bi-wallet2',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'paid' => 'Đã thanh toán',
            'partial' => 'Một phần',
            'pending' => 'Chờ thanh toán',
            'failed' => 'Thất bại',
            'refunded' => 'Đã hoàn tiền',
            default => $this->status,
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'paid' => 'bg-success-subtle text-success border-success',
            'partial' => 'bg-warning-subtle text-warning border-warning',
            'pending' => 'bg-secondary-subtle text-secondary border-secondary',
            'failed' => 'bg-danger-subtle text-danger border-danger',
            'refunded' => 'bg-info-subtle text-info border-info',
            default => 'bg-secondary-subtle text-secondary border-secondary',
        };
    }
}