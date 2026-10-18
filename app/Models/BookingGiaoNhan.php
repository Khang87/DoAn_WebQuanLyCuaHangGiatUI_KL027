<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingGiaoNhan extends Model
{
    protected $table = 'BookingGiaoNhan';

    protected $primaryKey = 'BookingGiaoNhanID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'BookingID', 'DonHangID', 'BookingKiemKeID', 'LoaiGiaoNhan', 'HinhThuc', 'DiaChi', 'NhanVienID',
        'ThoiGianDuKien', 'ThoiGianThucTe', 'PhiGiaoNhan', 'TrangThai', 'NgayTao',
    ];

    protected $casts = [
        'DonHangID' => 'integer',
        'BookingKiemKeID' => 'integer',
        'ThoiGianDuKien' => 'datetime',
        'ThoiGianThucTe' => 'datetime',
        'PhiGiaoNhan' => 'float',
        'NgayTao' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'BookingID');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function kiemKe()
    {
        return $this->belongsTo(BookingKiemKe::class, 'BookingKiemKeID');
    }
}
