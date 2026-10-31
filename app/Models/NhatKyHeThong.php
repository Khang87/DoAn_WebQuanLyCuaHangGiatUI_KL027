<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            'BookingKiemKe' => 'Kiểm tra theo đặt lịch',
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

    /**
     * Màu badge theo nhóm nghiệp vụ để phân biệt nhanh đối tượng bị tác động.
     */
    public static function toneForBang(string $bang): string
    {
        return match ($bang) {
            'DonHang', 'ChiTietDonHang' => 'bg-success-subtle text-success-emphasis',
            'Booking', 'BookingKiemKe' => 'bg-primary-subtle text-primary-emphasis',
            'BookingGiaoNhan', 'GiaoNhan' => 'bg-warning-subtle text-warning-emphasis',
            'HoaDon', 'ThanhToan' => 'bg-info-subtle text-info-emphasis',
            default => 'bg-light text-dark border',
        };
    }

    /**
     * Khóa chính và cột mã nghiệp vụ của bảng được ghi nhật ký.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function targetForBang(string $bang): ?array
    {
        return match ($bang) {
            'DonHang' => ['DonHangID', 'MaDonHang'],
            'Booking' => ['BookingID', 'MaBooking'],
            'HoaDon' => ['HoaDonID', 'MaHoaDon'],
            'TaiKhoan' => ['TaiKhoanID', 'TenDangNhap'],
            default => null,
        };
    }

    /**
     * Mã của bản ghi bị tác động (DH0133, BK0353...) cho cả một trang nhật ký.
     * Gộp theo bảng nên mỗi bảng chỉ truy vấn một lần, không load theo từng dòng.
     *
     * @param  Collection<int, self>  $logs
     * @return array<int, string>  NhatKyID => mã hiển thị
     */
    public static function targetCodes(Collection $logs): array
    {
        $codes = [];

        $grouped = $logs
            ->filter(fn (self $log): bool => $log->BanGhiID !== null
                && self::targetForBang((string) $log->BangDuLieu) !== null)
            ->groupBy('BangDuLieu');

        foreach ($grouped as $bang => $rows) {
            [$primaryKey, $codeColumn] = self::targetForBang((string) $bang);
            $found = DB::table($bang)
                ->whereIn($primaryKey, $rows->pluck('BanGhiID')->unique()->all())
                ->pluck($codeColumn, $primaryKey);

            foreach ($rows as $log) {
                $code = $found[$log->BanGhiID] ?? null;
                if ($code !== null) {
                    $codes[$log->NhatKyID] = (string) $code;
                }
            }
        }

        return $codes;
    }
}
