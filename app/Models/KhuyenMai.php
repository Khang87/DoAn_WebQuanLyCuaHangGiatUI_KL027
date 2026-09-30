<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhuyenMai extends Model
{
    /** Giảm theo phần trăm. */
    public const DISCOUNT_PERCENTAGE = 'Phần trăm';

    /** Giảm theo số tiền cố định. */
    public const DISCOUNT_FIXED = 'Tiền mặt';

    protected $table = 'KhuyenMai';
    protected $primaryKey = 'KhuyenMaiID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'MaKhuyenMai', 'TenKhuyenMai', 'LoaiKhuyenMai', 'GiaTriGiam',
        'GiaTriDonToiThieu', 'MucGiamToiDa', 'SoLuongSuDung', 'DieuKienApDung',
        'NgayBatDau', 'NgayKetThuc', 'TrangThai', 'KhuyenMaiID'];

    protected $casts = [
        'GiaTriGiam' => 'float',
        'GiaTriDonToiThieu' => 'float',
        'MucGiamToiDa' => 'float',
        'SoLuongSuDung' => 'integer',
        'NgayBatDau' => 'date',
        'NgayKetThuc' => 'date',
    ];

    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'KhuyenMaiID');
    }

    /* ---------------------------------------------------------------------
     | Alias tiếng Anh cho tầng trên
     |---------------------------------------------------------------------*/

    public function getNameAttribute(): ?string
    {
        return $this->TenKhuyenMai;
    }

    public function getCodeAttribute(): ?string
    {
        return $this->MaKhuyenMai;
    }

    public function isValid(): bool
    {
        if ($this->TrangThai !== 'Hoạt động') {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->NgayBatDau && $this->NgayBatDau->startOfDay() > $today) {
            return false;
        }

        if ($this->NgayKetThuc && $this->NgayKetThuc->endOfDay() < $today) {
            return false;
        }

        // SoLuongSuDung = null nghĩa là dùng vô hạn.
        if ($this->SoLuongSuDung !== null && $this->SoLuongSuDung <= 0) {
            return false;
        }

        return true;
    }

    public function getIsValidAttribute(): bool
    {
        return $this->isValid();
    }

    /**
     * Tìm khuyến mãi theo mã (không phân biệt hoa thường).
     */
    public static function findByCode(?string $code): ?self
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        // Nháy kép bắt buộc: raw SQL không qua wrapper của Eloquent nên
        // PostgreSQL sẽ hạ `MaKhuyenMai` thành `makhuyenmai` và báo thiếu cột.
        return static::whereRaw('LOWER("MaKhuyenMai") = ?', [mb_strtolower($code)])->first();
    }

    /**
     * Điều kiện lưu ở `DieuKienApDung` có yêu cầu "đơn hàng đầu tiên" không.
     */
    public function isFirstOrderOnly(): bool
    {
        $condition = mb_strtolower((string) $this->DieuKienApDung);

        return str_contains($condition, 'first_order_only')
            || str_contains($condition, 'don hang dau tien')
            || str_contains($condition, 'đơn hàng đầu tiên');
    }

    public function isApplicableForCustomer(?KhachHang $customer, float $subtotal, ?int $excludeOrderId = null): bool
    {
        return $this->rejectionReasonForCustomer($customer, $subtotal, $excludeOrderId) === null;
    }

    /**
     * Lý do khuyến mãi bị loại (null nghĩa là dùng được).
     */
    public function rejectionReasonForCustomer(?KhachHang $customer, float $subtotal, ?int $excludeOrderId = null): ?string
    {
        if (! $this->isValid()) {
            return 'Chương trình khuyến mãi không còn hiệu lực.';
        }

        if ($this->GiaTriDonToiThieu !== null && $subtotal < (float) $this->GiaTriDonToiThieu) {
            return 'Đơn hàng chưa đạt giá trị tối thiểu để dùng mã khuyến mãi.';
        }

        if ($this->isFirstOrderOnly() && $this->customerAlreadyOrderedBefore($customer, $excludeOrderId)) {
            return 'Mã khuyến mãi chỉ áp dụng cho đơn hàng đầu tiên của khách hàng.';
        }

        return null;
    }

    /**
     * Khách đã có đơn hàng nào khác ngoài đơn đang xét hay chưa.
     */
    private function customerAlreadyOrderedBefore(?KhachHang $customer, ?int $excludeOrderId = null): bool
    {
        if (! $customer) {
            return true;
        }

        return DonHang::where('KhachHangID', $customer->KhachHangID)
            ->when($excludeOrderId !== null, fn ($query) => $query->whereKeyNot($excludeOrderId))
            ->exists();
    }

    public function calculateDiscountFor(float $subtotal, ?KhachHang $customer = null, ?int $excludeOrderId = null): float
    {
        if (! $this->isApplicableForCustomer($customer, $subtotal, $excludeOrderId)) {
            return 0;
        }

        return $this->calculateDiscount($subtotal);
    }

    /**
     * Tổng tiền giảm cho tạm tính đã cho.
     */
    public function calculateDiscount(float $orderAmount): float
    {
        if (! $this->isValid()) {
            return 0;
        }

        if ($this->GiaTriDonToiThieu !== null && $orderAmount < (float) $this->GiaTriDonToiThieu) {
            return 0;
        }

        $discount = match ($this->LoaiKhuyenMai) {
            self::DISCOUNT_PERCENTAGE => $orderAmount * ((float) $this->GiaTriGiam / 100),
            self::DISCOUNT_FIXED => (float) $this->GiaTriGiam,
            default => 0.0,
        };

        if ($this->MucGiamToiDa && $discount > (float) $this->MucGiamToiDa) {
            $discount = (float) $this->MucGiamToiDa;
        }

        return $discount;
    }

    /**
     * Ghi nhận một lượt sử dụng.
     */
    public function markUsed(): void
    {
        if ($this->SoLuongSuDung !== null) {
            $this->increment('SoLuongSuDung');
        }
    }

    /**
     * Hoàn lại một lượt sử dụng (khi bỏ mã khuyến mãi hoặc xóa đơn).
     */
    public function markUnused(): void
    {
        if ($this->SoLuongSuDung !== null && $this->SoLuongSuDung > 0) {
            $this->decrement('SoLuongSuDung');
        }
    }

    /**
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
        return self::discountTypeOptions()[$this->LoaiKhuyenMai] ?? 'Không xác định';
    }

    public function discountTypeBadgeClass(): string
    {
        return match ($this->LoaiKhuyenMai) {
            self::DISCOUNT_PERCENTAGE => 'bg-primary-subtle text-primary-emphasis border border-primary',
            self::DISCOUNT_FIXED => 'bg-purple-subtle text-purple-emphasis border border-purple',
            default => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
        };
    }

    public function discountValueLabel(): string
    {
        $value = (float) $this->GiaTriGiam;

        if ($this->LoaiKhuyenMai === self::DISCOUNT_PERCENTAGE) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
        }

        return number_format($value) . ' VNĐ';
    }

    public function discountSummary(): string
    {
        $summary = $this->discountValueLabel();

        if ($this->LoaiKhuyenMai === self::DISCOUNT_PERCENTAGE && (float) $this->MucGiamToiDa > 0) {
            $summary .= ' (tối đa ' . number_format((float) $this->MucGiamToiDa) . ' VNĐ)';
        }

        return $summary;
    }

    public function remainingCodes(): ?int
    {
        if ($this->SoLuongSuDung === null) {
            return null;
        }

        return max(0, (int) $this->SoLuongSuDung);
    }

    public function usageLabel(): string
    {
        return ($this->SoLuongSuDung ?? '∞') . ' lượt';
    }

    public function getStatusLabelAttribute(): string
    {
        return (string) $this->TrangThai;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->TrangThai) {
            'Hoạt động' => 'bg-success-subtle text-success-emphasis border border-success',
            'Tạm ngưng' => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
            default => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }
}
