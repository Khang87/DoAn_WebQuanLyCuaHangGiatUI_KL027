<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NhanVien extends Model
{
    protected $table = 'NhanVien';
    protected $primaryKey = 'NhanVienID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayVaoLam';

    protected $fillable = [
        'HoTen', 'SoDienThoai', 'Email', 'DiaChi', 'ChucDanh', 'NgayVaoLam', 'TrangThai', 'NhanVienID'];

    protected $casts = [
        'NgayVaoLam' => 'date',
    ];

    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'NhanVienID');
    }

    public function giaoNhans()
    {
        return $this->hasMany(GiaoNhan::class, 'NhanVienID');
    }

    public function taiKhoans()
    {
        return $this->hasMany(TaiKhoan::class, 'NhanVienID');
    }

    public function lichSuThayDoiHoaDons()
    {
        return $this->hasMany(LichSuThayDoiHoaDon::class, 'TaiKhoanID');
    }
}