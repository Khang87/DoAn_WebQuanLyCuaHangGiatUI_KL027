<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Trạng thái đơn đã quyết toán: tiền đã chốt nên chỉ được xem.
     *
     * @return array<int, string>
     */
    public static function settledStatuses(): array
    {
        return OrderStatus::settledValues();
    }

    protected $fillable = [
        'code',
        'customer_id',
        'employee_id',
        'service_id',
        'promotion_id',
        'booking_id',
        'points_used',
        'weight_kg',
        'quantity_items',
        'subtotal',
        'discount_by_promotion',
        'discount_by_points',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_by_promotion' => 'decimal:2',
        'discount_by_points' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'points_used' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Hóa đơn của đơn. Nạp cả bản ghi đã xoá mềm vì hóa đơn đã thanh toán
     * rồi vẫn phải khoá đơn.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->withTrashed();
    }

    /**
     * Đặt lịch đã được chuyển thành đơn hàng này.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Đơn đã ở trạng thái quyết toán (hoàn thành) chưa?
     */
    public function isSettledByStatus(): bool
    {
        return OrderStatus::parse($this->status)->isSettled();
    }

    /**
     * Đơn đã có hóa đơn được thanh toán (kể cả hóa đơn đã bị xoá mềm) chưa?
     */
    public function hasPaidInvoice(): bool
    {
        if (array_key_exists('invoice', $this->relations)) {
            return InvoiceStatus::valueIsPaid($this->getRelation('invoice')?->status);
        }

        return Invoice::withTrashed()
            ->where('order_id', $this->id)
            ->whereIn('status', InvoiceStatus::paidValues())
            ->exists();
    }

    /**
     * Định nghĩa DUY NHẤT của "đơn đã quyết toán": hoàn thành HOẶC đã có hóa đơn
     * thanh toán. Đơn đã quyết toán là chỉ đọc: không sửa, không xoá, không đổi
     * trạng thái đi làm thay đổi số tiền.
     */
    public function isLocked(): bool
    {
        return $this->isSettledByStatus() || $this->hasPaidInvoice();
    }

    public function canEdit(): bool
    {
        return ! $this->isLocked();
    }

    public function canDelete(): bool
    {
        return ! $this->isLocked();
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->isLocked();
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
