<?php

namespace App\Models;

use App\Support\PermissionCache;
use App\Support\QuyenMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Tài khoản trong bảng `TaiKhoan` của Supabase.
 *
 * Model này là lớp dữ liệu thuần cho bảng `TaiKhoan`; lớp dùng để đăng nhập và
 * kiểm tra quyền là {@see User} (cùng bảng, kế thừa Authenticatable).
 */
class TaiKhoan extends Model
{
    protected $table = 'TaiKhoan';

    protected $primaryKey = 'TaiKhoanID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at. */
    public const CREATED_AT = 'NgayTao';

    protected $fillable = [
        'TenDangNhap', 'MatKhau', 'Email', 'SoDienThoai', 'NhanVienID',
        'KhachHangID', 'TrangThai', 'NgayTao', 'UserAuthId', 'TaiKhoanID'];

    protected $casts = [
        'TaiKhoanID' => 'integer',
        'NhanVienID' => 'integer',
        'KhachHangID' => 'integer',
        'NgayTao' => 'datetime',
    ];

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }

    public function taiKhoanVaiTros()
    {
        return $this->hasMany(TaiKhoanVaiTro::class, 'TaiKhoanID');
    }

    /**
     * Vai trò được cấp cho tài khoản qua bảng nối `TaiKhoan_VaiTro`.
     */
    public function vaiTros(): BelongsToMany
    {
        return $this->belongsToMany(
            VaiTro::class,
            'TaiKhoan_VaiTro',
            'TaiKhoanID',
            'VaiTroID',
        );
    }

    /**
     * Quyền của tài khoản, gộp từ mọi vai trò qua
     * `TaiKhoan_VaiTro` -> `VaiTro_Quyen` -> `Quyen`.
     *
     * Đây là helper truy vấn, không phải Eloquent relation: đường đi qua hai bảng
     * nối liên tiếp nên Laravel không biểu diễn được bằng `belongsToMany`.
     * Dùng {@see TaiKhoan::permissionCodes()} khi chỉ cần danh sách mã quyền vì
     * bản đó đọc từ cache.
     *
     * @return Collection<int, Quyen>
     */
    public function quyens(): Collection
    {
        return Quyen::query()
            ->join('VaiTro_Quyen', 'VaiTro_Quyen.QuyenID', '=', 'Quyen.QuyenID')
            ->join('TaiKhoan_VaiTro', 'TaiKhoan_VaiTro.VaiTroID', '=', 'VaiTro_Quyen.VaiTroID')
            ->where('TaiKhoan_VaiTro.TaiKhoanID', $this->getKey())
            ->where('Quyen.TrangThai', 'Hoạt động')
            ->select('Quyen.*')
            ->distinct()
            ->get();
    }

    public function thongBaos()
    {
        return $this->hasMany(ThongBao::class, 'TaiKhoanID');
    }

    public function tinNhans()
    {
        return $this->hasMany(TinNhan::class, 'NguoiGuiID');
    }

    public function tinNhansNhan()
    {
        return $this->hasMany(TinNhan::class, 'NguoiNhanID');
    }

    public function lichSuThayDoiHoaDons()
    {
        return $this->hasMany(LichSuThayDoiHoaDon::class, 'TaiKhoanID');
    }

    /* ---------------------------------------------------------------------
     | Phân quyền (bản cache, dùng chung với User)
     |--------------------------------------------------------------------*/

    /**
     * Tên vai trò (TenVaiTro) đang được cấp, đọc từ cache.
     *
     * @return list<string>
     */
    public function roleNames(): array
    {
        return PermissionCache::roles($this->getKey());
    }

    /**
     * Mã quyền (MaQuyen) đang được cấp, đọc từ cache.
     *
     * @return list<string>
     */
    public function maQuyens(): array
    {
        return PermissionCache::quyens($this->getKey());
    }

    /**
     * Mã quyền dạng dùng trong code (vd `orders.view`).
     *
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        return QuyenMapper::coveredCodes($this->maQuyens());
    }

    /**
     * Kiểm tra một mã quyền dạng dùng trong code.
     */
    public function canPermission(string $code): bool
    {
        return QuyenMapper::covers($code, $this->maQuyens());
    }
}
