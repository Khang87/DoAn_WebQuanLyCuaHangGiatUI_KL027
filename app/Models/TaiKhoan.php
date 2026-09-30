<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}