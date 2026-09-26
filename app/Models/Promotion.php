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

    /** Khoá điều kiện trong mảng `conditions`: chỉ áp dụng cho đơn đầu tiên của khách. */
    public const CONDITION_FIRST_ORDER_ONLY = 'first_order_only';

    /** Giảm theo phần trăm. */
    public const DISCOUNT_PERCENTAGE = 'percentage';

    /** Giảm theo số tiền cố định. */
    public const DISCOUNT_FIXED = 'fixed';

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
     * Điều kiện `conditions` có được bật không (mảng rỗng hoặc null là không).
     */
    public function hasConditions(): bool
    {
        return is_array($this->conditions) && $this->conditions !== [];
    }

    /**
     * Voucher chỉ dành cho đơn hàng đầu tiên của khách hàng hay không.
     */
    public function isFirstOrderOnly(): bool
    {
        return (bool) ($this->conditions[self::CONDITION_FIRST_ORDER_ONLY] ?? false);
    }

    /**
     * Voucher này có dùng được cho khách đang xét không?
     *
     *   - min_order_amount: tạm tính của đơn phải đạt ngưỡng tối thiểu.
     *   - first_order_only: khách chưa có đơn nào trước đơn đang xét.
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
        if ($subtotal < (float) $this->min_order_amount) {
            return 'Đơn hàng chưa đạt giá trị tối thiểu để dùng voucher.';
        }

        if ($this->isFirstOrderOnly() && $this->customerAlreadyOrderedBefore($customer, $excludeOrderId)) {
            return 'Voucher chỉ áp dụng cho đơn hàng đầu tiên của khách hàng.';
        }

        return null;
    }

    /**
     * Khách đã có đơn hàng nào khác ngoài đơn đang xét hay chưa.
     */
    private function customerAlreadyOrderedBefore(?Customer $customer, ?int $excludeOrderId = null): bool
    {
        if (! $customer) {
            return true;
        }

        return Order::where('customer_id', $customer->id)
            ->when($excludeOrderId !== null, fn ($query) => $query->whereKeyNot($excludeOrderId))
            ->exists();
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
            self::DISCOUNT_PERCENTAGE => $orderAmount * ((float) $this->discount_value / 100),
            self::DISCOUNT_FIXED => (float) $this->discount_value,
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

    /**
     * Danh sách loại giảm hợp lệ (nguồn duy nhất cho form + hiển thị).
     *
     * @return array<string, string>
     */
    public static function discountTypeOptions(): array
    {
        return [
            self::DISCOUNT_PERCENTAGE => 'Phần trăm (%)',
            self::DISCOUNT_FIXED => 'Số tiền cố định (VNĐ)',
        ];
    }

    public function discountTypeLabel(): string
    {
        return self::discountTypeOptions()[$this->discount_type] ?? 'Không xác định';
    }

    public function discountTypeBadgeClass(): string
    {
        return match ($this->discount_type) {
            self::DISCOUNT_PERCENTAGE => 'bg-primary-subtle text-primary-emphasis border border-primary',
            self::DISCOUNT_FIXED => 'bg-purple-subtle text-purple-emphasis border border-purple',
            default => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
        };
    }

    /**
     * Giá trị giảm đã định dạng: "20%" hoặc "50.000 VNĐ".
     */
    public function discountValueLabel(): string
    {
        $value = (float) $this->discount_value;

        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
        }

        return number_format($value) . ' VNĐ';
    }

    /**
     * Mô tả đầy đủ luôn có trần giảm: "20% (tối đa 50,000 VNĐ)" hoặc "50,000 VNĐ".
     */
    public function discountSummary(): string
    {
        $summary = $this->discountValueLabel();

        if ($this->discount_type === self::DISCOUNT_PERCENTAGE && (float) $this->max_discount > 0) {
            $summary .= ' (tối đa ' . number_format((float) $this->max_discount) . ' VNĐ)';
        }

        return $summary;
    }

    /**
     * Số mã còn phát được (null = phát vô hạn).
     */
    public function remainingCodes(): ?int
    {
        if ($this->quantity === null) {
            return null;
        }

        return max(0, (int) $this->quantity - (int) $this->used_count);
    }

    /**
     * "Đã dùng / tối đa" dùng cho cột hạn mức ở bảng danh sách.
     */
    public function usageLabel(): string
    {
        return $this->used_count . '/' . ($this->usage_limit ?: '∞');
    }
}