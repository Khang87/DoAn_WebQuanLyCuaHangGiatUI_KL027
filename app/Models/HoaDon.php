<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoaDon extends Model
{
    protected $table = 'HoaDon';
    protected $primaryKey = 'HoaDonID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayLap';

    protected $fillable = [
        'MaHoaDon', 'DonHangID', 'TongTien', 'GiamGia', 'PhiGiaoHang',
        'ThanhTien', 'NgayLap', 'TrangThai', 'HoaDonID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'TongTien' => 'float',
        'GiamGia' => 'float',
        'PhiGiaoHang' => 'float',
        'ThanhTien' => 'float',
        'NgayLap' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function lichSuThayDoiHoaDons()
    {
        return $this->hasMany(LichSuThayDoiHoaDon::class, 'HoaDonID');
    }
}