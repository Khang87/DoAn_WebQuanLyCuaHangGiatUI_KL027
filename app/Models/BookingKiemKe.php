<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingKiemKe extends Model
{
    protected $table = 'BookingKiemKe';

    protected $primaryKey = 'BookingKiemKeID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'BookingID', 'DuLieuThucTe', 'CoChenhLech', 'PhienBan',
        'CapNhatLuc',
    ];

    protected $casts = [
        'DuLieuThucTe' => 'array',
        'CoChenhLech' => 'boolean',
        'PhienBan' => 'integer',
        'CapNhatLuc' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'BookingID');
    }
}
