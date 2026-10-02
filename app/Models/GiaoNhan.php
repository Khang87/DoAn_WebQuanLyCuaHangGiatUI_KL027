<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiaoNhan extends Model
{
    protected $table = 'GiaoNhan';

    protected $primaryKey = 'GiaoNhanID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'DonHangID', 'NhanVienID', 'LoaiGiaoNhan', 'HinhThuc', 'DiaChi',
        'ThoiGianDuKien', 'ThoiGianThucTe', 'PhiGiaoNhan', 'TrangThai', 'GhiChu', 'GiaoNhanID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'NhanVienID' => 'integer',
        'PhiGiaoNhan' => 'float',
        'ThoiGianDuKien' => 'datetime',
        'ThoiGianThucTe' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID');
    }
}
