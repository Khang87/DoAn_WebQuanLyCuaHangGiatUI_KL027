<?php

namespace App\Models;

use App\Enums\PaymentStatus;
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
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
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
        return PaymentStatus::labelFor($this->status);
    }

    public function getStatusBadgeClass(): string
    {
        return PaymentStatus::badgeClassFor($this->status);
    }

    public function getStatusIcon(): string
    {
        return PaymentStatus::iconFor($this->status);
    }

    /**
     * Khoản thu đã ghi nhận tiền thật chưa? "partial" là đang thu dở nên vẫn
     * được sửa; "failed"/"refunded" không phải tiền đã vào quỹ.
     */
    public function isSettled(): bool
    {
        return PaymentStatus::parse($this->status)->isPaid();
    }

    /**
     * Đơn/hóa đơn liên quan đã quyết toán thì khoản thu này cũng bị khoá theo.
     */
    public function hasSettledDocument(): bool
    {
        $invoice = $this->invoice ?? $this->order?->invoice;

        if ($invoice?->isPaid()) {
            return true;
        }

        return (bool) $this->order?->isLocked();
    }

    /**
     * Khoản thu đã thu tiền thật là chứng từ lịch sử: chỉ đọc, không sửa/xoá.
     */
    public function isLocked(): bool
    {
        return $this->isSettled() || $this->hasSettledDocument();
    }

    public function canEdit(): bool
    {
        if (! $this->isLocked()) {
            return true;
        }

        return $this->viewerCanOverrideSettled('payments.edit_paid');
    }

    public function canDelete(): bool
    {
        if (! $this->isLocked()) {
            return true;
        }

        return $this->viewerCanOverrideSettled('payments.delete_paid');
    }

    /**
     * Người đang đăng nhập có mở khoá được khoản thu đã quyết toán hay không.
     * Chỉ Chủ cửa hàng (người giữ payments.edit_paid / payments.delete_paid) mở khoá được.
     */
    private function viewerCanOverrideSettled(string $code): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canPermission($code);
    }

    public function getCanEditAttribute(): bool
    {
        return $this->canEdit();
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->canDelete();
    }
}