<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnMethod;
use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    protected $table = 'DonHang';

    protected $primaryKey = 'DonHangID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayTao';

    public const UPDATED_AT = 'NgayCapNhat';

    protected $fillable = [
        'MaDonHang', 'BookingID', 'KhachHangID', 'NhanVienID', 'TrangThai',
        'TongTien', 'DiemSuDung', 'TienGiamDoDiem', 'KhuyenMaiID',
        'TienGiamKhuyenMai', 'PhiGiaoHang', 'ThanhTien', 'GhiChu',
        'NgayTao', 'NgayCapNhat', 'IdempotencyKey', 'DonHangID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'BookingID' => 'integer',
        'KhachHangID' => 'integer',
        'NhanVienID' => 'integer',
        'KhuyenMaiID' => 'integer',
        'TongTien' => 'float',
        'DiemSuDung' => 'integer',
        'TienGiamDoDiem' => 'float',
        'TienGiamKhuyenMai' => 'float',
        'PhiGiaoHang' => 'float',
        'ThanhTien' => 'float',
        'NgayTao' => 'datetime',
        'NgayCapNhat' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'BookingID');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID');
    }

    public function khuyenMai()
    {
        return $this->belongsTo(KhuyenMai::class, 'KhuyenMaiID');
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class, 'DonHangID');
    }

    public function giaoNhans()
    {
        return $this->hasMany(GiaoNhan::class, 'DonHangID');
    }

    public function requiresHomeDelivery(): bool
    {
        if ($this->relationLoaded('booking')) {
            if ($this->booking !== null) {
                return $this->booking->HinhThucTraDo === ReturnMethod::Home->value;
            }
        } else {
            $bookingReturnMethod = $this->booking()->value('HinhThucTraDo');
            if ($bookingReturnMethod !== null) {
                return $bookingReturnMethod === ReturnMethod::Home->value;
            }
        }

        return $this->relationLoaded('giaoNhans')
            ? $this->giaoNhans->contains('LoaiGiaoNhan', 'GIAO_DO')
            : $this->giaoNhans()->where('LoaiGiaoNhan', 'GIAO_DO')->exists();
    }

    public function hasActiveReturnDelivery(): bool
    {
        return $this->relationLoaded('giaoNhans')
            ? $this->giaoNhans->contains(fn (GiaoNhan $delivery): bool => $delivery->LoaiGiaoNhan === 'GIAO_DO'
                && DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan) !== DeliveryStatus::Cancelled)
            : $this->giaoNhans()
                ->where('LoaiGiaoNhan', 'GIAO_DO')
                ->where('TrangThai', '!=', DeliveryStatus::Cancelled->dbValue())
                ->exists();
    }

    public function hoaDons()
    {
        return $this->hasMany(HoaDon::class, 'DonHangID');
    }

    public function thanhToans()
    {
        return $this->hasMany(ThanhToan::class, 'DonHangID');
    }

    public function thongBaos()
    {
        return $this->hasMany(ThongBao::class, 'DonHangID');
    }

    public function tinNhans()
    {
        return $this->hasMany(TinNhan::class, 'DonHangID');
    }

    public function danhGia()
    {
        return $this->hasOne(DanhGia::class, 'DonHangID');
    }

    /* ---------------------------------------------------------------------
     | Alias tiếng Anh cho tầng trên (controller / service / view)
     |---------------------------------------------------------------------*/

    public function customer()
    {
        return $this->khachHang();
    }

    public function employee()
    {
        return $this->nhanVien();
    }

    public function promotion()
    {
        return $this->khuyenMai();
    }

    public function items()
    {
        return $this->chiTietDonHangs();
    }

    public function payments()
    {
        return $this->thanhToans();
    }

    /* ---------------------------------------------------------------------
     | Thuộc tính hiển thị
     |---------------------------------------------------------------------*/

    /**
     * Mã đơn hàng hiển thị trong giao diện (tương đương cột `code` cũ).
     */
    public function getCodeAttribute(): ?string
    {
        return $this->MaDonHang;
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->isPaid();
    }

    public function getCanEditAttribute(): bool
    {
        return ! $this->isLocked();
    }

    public function getCanDeleteAttribute(): bool
    {
        return ! $this->isLocked();
    }

    public function getStatusAttribute(): ?string
    {
        return $this->TrangThai;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->GhiChu;
    }

    public function getSubtotalAttribute(): ?float
    {
        return $this->TongTien;
    }

    public function getTotalAmountAttribute(): ?float
    {
        return $this->ThanhTien;
    }

    public function getPointsUsedAttribute(): int
    {
        return (int) $this->DiemSuDung;
    }

    public function getCreatedAtAttribute(): mixed
    {
        return $this->NgayTao;
    }

    public function getUpdatedAtAttribute(): mixed
    {
        return $this->NgayCapNhat;
    }

    public function getDiscountByPromotionAttribute(): ?float
    {
        return $this->TienGiamKhuyenMai;
    }

    public function getDiscountByPointsAttribute(): ?float
    {
        return $this->TienGiamDoDiem;
    }

    /* ---------------------------------------------------------------------
     | Nghiệp vụ trạng thái
     |---------------------------------------------------------------------*/

    public function statusEnum(): OrderStatus
    {
        return OrderStatus::parse($this->TrangThai);
    }

    /**
     * Đơn đã thu tiền: tiền đã chốt nên không được sửa/xoá nếu không có quyền
     * của Chủ cửa hàng.
     */
    public function isPaid(): bool
    {
        if ($this->statusEnum()->isSettled()) {
            return true;
        }

        if (array_key_exists('has_paid_invoice', $this->attributes)) {
            return (bool) $this->attributes['has_paid_invoice'];
        }

        if ($this->relationLoaded('hoaDons')) {
            return $this->hoaDons->contains(
                fn (HoaDon $invoice): bool => $invoice->TrangThai === InvoiceStatus::Paid->value
            );
        }

        return $this->hoaDons()->where('TrangThai', InvoiceStatus::Paid->value)->exists();
    }

    /**
     * Đơn đã quyết toán: chỉ đọc.
     */
    public function isLocked(): bool
    {
        if ($this->isPaid()) {
            return true;
        }

        if (array_key_exists('has_successful_payment', $this->attributes)) {
            return (bool) $this->attributes['has_successful_payment'];
        }

        if ($this->relationLoaded('thanhToans')) {
            return $this->thanhToans->contains(
                fn (ThanhToan $payment): bool => $payment->TrangThai === PaymentStatus::Paid->value
            );
        }

        return $this->thanhToans()->where('TrangThai', PaymentStatus::Paid->value)->exists();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusEnum()->label();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return $this->statusEnum()->badgeClass();
    }

    public function getStatusIconAttribute(): string
    {
        return $this->statusEnum()->icon();
    }

    /**
     * Tổng số lượng mặt hàng của đơn.
     */
    public function totalQuantity(): float
    {
        return (float) $this->chiTietDonHangs->sum(fn (ChiTietDonHang $item) => (float) $item->SoLuong);
    }
}
