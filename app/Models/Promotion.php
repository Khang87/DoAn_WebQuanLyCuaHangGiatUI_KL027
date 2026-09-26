<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount',
        'usage_limit',
        'used_count',
        'quantity',
        'conditions',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'quantity' => 'integer',
        'conditions' => 'array',
        'starts_at' => 'date',
        'expires_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Khoá điều kiện trong cột JSON `conditions`: voucher chỉ dùng được cho
     * đơn hàng đầu tiên của khách.
     */
    public const CONDITION_FIRST_ORDER_ONLY = 'first_order_only';

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'promotions_coupons');
    }

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at && $this->starts_at > now()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < now()) {
            return false;
        }

        // Đã hết lượt sử dụng (usage_limit = null nghĩa là không giới hạn).
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        // Đã phát đủ số lượng mã (quantity = null nghĩa là phát vô hạn).
        if ($this->quantity !== null && $this->used_count >= $this->quantity) {
            return false;
        }

        return true;
    }

    /**
     * Voucher có giới hạn chỉ dùng cho đơn hàng đầu tiên của khách không?
     */
    public function isFirstOrderOnly(): bool
    {
        return (bool) data_get($this->conditions, self::CONDITION_FIRST_ORDER_ONLY, false);
    }

    /**
     * Điều kiện `conditions` có được bật không (mảng rỗng hoặc null là không).
     */
    public function hasConditions(): bool
    {
        return is_array($this->conditions) && $this->conditions !== [];
    }

    /**
     * Voucher này có dùng được cho khách đang xét không?
     *
     *   - first_order_only: khách phải chưa có đơn hàng nào (bỏ qua chính đơn
     *     đang được sửa nếu có $excludeOrderId).
     *   - min_order_amount: tạm tính của đơn phải đạt ngưỡng tối thiểu.
     *
     * Voucher không khai báo điều kiện nào thì luôn dùng được như trước.
     */
    public function isApplicableForCustomer(?Customer $customer, float $subtotal, ?int $excludeOrderId = null): bool
    {
        return $this->rejectionReasonForCustomer($customer, $subtotal, $excludeOrderId) === null;
    }

    /**
     * Lý do voucher bị loại (null nghĩa là dùng được).
     */
    public function rejectionReasonForCustomer(?Customer $customer, float $subtotal, ?int $excludeOrderId = null): ?string
    {
        if ($this->isFirstOrderOnly()) {
            if (! $customer) {
                return 'Voucher chỉ áp dụng cho đơn hàng đầu tiên của khách hàng.';
            }

            $hasOtherOrders = Order::where('customer_id', $customer->id)
                ->when($excludeOrderId, fn ($query) => $query->where('id', '!=', $excludeOrderId))
                ->exists();

            if ($hasOtherOrders) {
                return 'Voucher chỉ áp dụng cho đơn hàng đầu tiên của khách hàng.';
            }
        }

        if ($subtotal < (float) $this->min_order_amount) {
            return 'Đơn hàng chưa đạt giá trị tối thiểu để dùng voucher.';
        }

        return null;
    }

    /**
     * Tổng tiền giảm cho tạm tính đã cho, áp dụng cả điều kiện của khách hàng.
     */
    public function calculateDiscountFor(float $subtotal, ?Customer $customer = null, ?int $excludeOrderId = null): float
    {
        if (! $this->isApplicableForCustomer($customer, $subtotal, $excludeOrderId)) {
            return 0;
        }

        return $this->calculateDiscount($subtotal);
    }

    public function getIsValidAttribute(): bool
    {
        return $this->isValid();
    }

    /**
     * Tìm khuyến mãi theo mã code (không phân biệt hoa thường).
     */
    public static function findByCode(?string $code): ?self
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        return static::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();
    }

    /**
     * Ghi nhận một lượt sử dụng voucher.
     */
    public function markUsed(): void
    {
        $this->increment('used_count');
    }

    /**
     * Hoàn lại một lượt sử dụng voucher (khi bỏ voucher hoặc xóa đơn).
     */
    public function markUnused(): void
    {
        if ($this->used_count > 0) {
            $this->decrement('used_count');
        }
    }

    public function calculateDiscount(float $orderAmount): float
    {
        if (!$this->isValid()) {
            return 0;
        }

        if ($orderAmount < $this->min_order_amount) {
            return 0;
        }

        $discount = match ($this->discount_type) {
            'percentage' => $orderAmount * ($this->discount_value / 100),
            'fixed' => $this->discount_value,
            default => 0,
        };

        if ($this->max_discount && $discount > $this->max_discount) {
            $discount = $this->max_discount;
        }

        return $discount;
    }

    public function getStatusLabelAttribute(): string
    {
        return RecordStatus::parse($this->status)->label();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return RecordStatus::parse($this->status)->badgeClass();
    }
}
