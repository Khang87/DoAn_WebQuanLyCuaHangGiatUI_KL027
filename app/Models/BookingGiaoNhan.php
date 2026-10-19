<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'BookingID');
    }

    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function kiemKe(): BelongsTo
    {
        return $this->belongsTo(BookingKiemKe::class, 'BookingKiemKeID');
    }

    public function nhanVien(): BelongsTo
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID', 'NhanVienID');
    }
}
