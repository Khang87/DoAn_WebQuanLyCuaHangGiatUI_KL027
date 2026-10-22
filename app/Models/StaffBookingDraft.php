<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffBookingDraft extends Model
{
    protected $table = 'StaffBookingDraft';
    protected $primaryKey = 'BookingID';
    public $incrementing = false;
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = ['BookingID', 'Payload', 'CoChenhLech', 'CapNhatLuc'];
    protected $casts = ['Payload' => 'array', 'CoChenhLech' => 'boolean', 'CapNhatLuc' => 'datetime'];
}
