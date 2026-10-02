<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DanhGia extends Model
{
    protected $table = 'DanhGia';

    protected $primaryKey = 'DanhGiaID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayDanhGia';

    protected $fillable = [
        'DonHangID', 'KhachHangID', 'SoSao', 'BinhLuan', 'NgayDanhGia', 'TrangThai', 'DanhGiaID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'KhachHangID' => 'integer',
        'SoSao' => 'integer',
        'NgayDanhGia' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }
}
