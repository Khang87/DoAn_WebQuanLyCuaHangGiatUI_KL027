<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LichSuThayDoiHoaDon extends Model
{
    protected $table = 'LichSuThayDoiHoaDon';

    protected $primaryKey = 'LichSuID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'ThoiGian';

    protected $fillable = [
        'HoaDonID', 'TaiKhoanID', 'ThoiGian', 'TruongThayDoi',
        'GiaTriCu', 'GiaTriMoi', 'LyDo', 'LichSuID'];

    protected $casts = [
        'HoaDonID' => 'integer',
        'TaiKhoanID' => 'integer',
        'ThoiGian' => 'datetime',
    ];

    public function hoaDon()
    {
        return $this->belongsTo(HoaDon::class, 'HoaDonID');
    }

    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'TaiKhoanID');
    }
}
