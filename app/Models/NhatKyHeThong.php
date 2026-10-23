<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NhatKyHeThong extends Model
{
    protected $table = 'NhatKyHeThong';

    protected $primaryKey = 'NhatKyID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'TaiKhoanID',
        'HanhDong',
        'BangDuLieu',
        'BanGhiID',
        'DuLieuCu',
        'DuLieuMoi',
        'LyDo',
        'ThoiGian',
        'IPAddress',
        'UserAgent',
    ];

    protected $casts = [
        'NhatKyID' => 'integer',
        'TaiKhoanID' => 'integer',
        'BanGhiID' => 'integer',
        'DuLieuCu' => 'array',
        'DuLieuMoi' => 'array',
        'ThoiGian' => 'datetime',
    ];

    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'TaiKhoanID', 'TaiKhoanID');
    }

    /**
     * Nhật ký lưu tên bảng vật lý (DonHang, BookingGiaoNhan...) nhưng người dùng
     * chỉ cần nhãn nghiệp vụ tiếng Việt, không cần mã nội bộ.
     */
    public static function labelForBang(string $bang): string
    {
        return match ($bang) {
            'DonHang' => 'Đơn hàng',
            'ChiTietDonHang' => 'Chi tiết đơn hàng',
            'Booking' => 'Lịch hẹn',
            'BookingGiaoNhan' => 'Giao nhận theo lịch hẹn',
            'BookingKiemKe' => 'Kiểm kê theo lịch hẹn',
            'TaiKhoan' => 'Tài khoản',
            'KhachHang' => 'Khách hàng',
            'NhanVien' => 'Nhân viên',
            'DichVu' => 'Dịch vụ',
            'LoaiDichVu' => 'Danh mục dịch vụ',
            'LoaiDoGiat' => 'Loại đồ giặt',
            'BangGia' => 'Bảng giá',
            'HoaDon' => 'Hóa đơn',
            'ThanhToan' => 'Thanh toán',
            'KhuyenMai' => 'Khuyến mãi',
            'MaGiamGia' => 'Mã giảm giá',
            'DanhGia' => 'Đánh giá',
            'GiaoNhan' => 'Giao nhận',
            'ThongBao' => 'Thông báo',
            'VaiTro' => 'Nhóm quyền',
            'Quyen' => 'Quyền hạn',
            default => Str::headline($bang),
        };
    }

    public function getBangDuLieuLabelAttribute(): string
    {
        return self::labelForBang((string) $this->BangDuLieu);
    }
}
