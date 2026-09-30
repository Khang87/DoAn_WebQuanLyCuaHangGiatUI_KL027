<?php

namespace App\Models;

use App\Models\NhanVien;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'Booking';
    protected $primaryKey = 'BookingID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayTao';

    public const UPDATED_AT = 'NgayCapNhat';

    protected $fillable = [
        'MaBooking', 'KhachHangID', 'HinhThucNhanDo', 'DiaChiNhan',
        'NgayHen', 'GioHen', 'GhiChu', 'TrangThai', 'NgayTao', 'NgayCapNhat',
        'IdempotencyKey', 'DichVuID', 'LoaiDoGiatID', 'DonViTinhID',
        'SoLuong', 'KhoiLuong', 'DonGia', 'ThanhTien', 'BookingID', 'NhanVienID'];

    protected $casts = [
        'BookingID' => 'integer',
        'KhachHangID' => 'integer',
        'DichVuID' => 'integer',
        'LoaiDoGiatID' => 'integer',
        'DonViTinhID' => 'integer',
        'NhanVienID' => 'integer',
        'SoLuong' => 'decimal:2',
        'KhoiLuong' => 'decimal:2',
        'DonGia' => 'decimal:2',
        'ThanhTien' => 'decimal:2',
        'NgayHen' => 'date',
        'GioHen' => 'datetime',
        'NgayTao' => 'datetime',
        'NgayCapNhat' => 'datetime',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }

    public function dichVu()
    {
        return $this->belongsTo(DichVu::class, 'DichVuID');
    }

    public function loaiDoGiat()
    {
        return $this->belongsTo(LoaiDoGiat::class, 'LoaiDoGiatID');
    }

    public function donViTinh()
    {
        return $this->belongsTo(DonViTinh::class, 'DonViTinhID');
    }

    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'BookingID');
    }

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID');
    }
}