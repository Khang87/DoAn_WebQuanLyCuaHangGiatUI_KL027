<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietBooking extends Model
{
    protected $table = 'ChiTietBooking';

    protected $primaryKey = 'ChiTietBookingID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'BookingID',
        'DichVuID',
        'LoaiDoGiatID',
        'DonViTinhID',
        'SoLuong',
        'KhoiLuong',
        'DonGia',
        'ThanhTien',
        'GhiChu',
    ];

    protected $casts = [
        'ChiTietBookingID' => 'integer',
        'BookingID' => 'integer',
        'DichVuID' => 'integer',
        'LoaiDoGiatID' => 'integer',
        'DonViTinhID' => 'integer',
        'SoLuong' => 'decimal:2',
        'KhoiLuong' => 'decimal:2',
        'DonGia' => 'decimal:2',
        'ThanhTien' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'BookingID', 'BookingID');
    }

    public function dichVu(): BelongsTo
    {
        return $this->belongsTo(DichVu::class, 'DichVuID', 'DichVuID');
    }

    public function loaiDoGiat(): BelongsTo
    {
        return $this->belongsTo(LoaiDoGiat::class, 'LoaiDoGiatID', 'LoaiDoGiatID');
    }

    public function donViTinh(): BelongsTo
    {
        return $this->belongsTo(DonViTinh::class, 'DonViTinhID', 'DonViTinhID');
    }
}
