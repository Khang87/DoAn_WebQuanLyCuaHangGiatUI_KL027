<?php

namespace App\Models;

use App\Enums\OrderStatus;
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

    /**
     * Lịch sử thay đổi trạng thái (bảng `donhang_trangthai`).
     */
    public function lichSuTrangThai()
    {
        return $this->hasMany(DonHangTrangthai::class, 'donhangid');
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
        return $this->statusEnum()->isSettled();
    }

    /**
     * Đơn đã quyết toán: chỉ đọc.
     */
    public function isLocked(): bool
    {
        return $this->isPaid();
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
