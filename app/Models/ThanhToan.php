<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThanhToan extends Model
{
    protected $table = 'ThanhToan';
    protected $primaryKey = 'ThanhToanID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'ThoiGian';

    protected $fillable = [
        'DonHangID', 'SoTien', 'PhuongThuc', 'MaGiaoDich', 'ThoiGian', 'TrangThai', 'GhiChu', 'ThanhToanID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'SoTien' => 'float',
        'ThoiGian' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }
}