<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TinNhan extends Model
{
    protected $table = 'TinNhan';

    protected $primaryKey = 'TinNhanID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'ThoiGianGui';

    protected $fillable = [
        'NguoiGuiID', 'NguoiNhanID', 'DonHangID', 'NoiDung', 'ThoiGianGui', 'TrangThai', 'TinNhanID'];

    protected $casts = [
        'NguoiGuiID' => 'integer',
        'NguoiNhanID' => 'integer',
        'DonHangID' => 'integer',
        'ThoiGianGui' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'NguoiGuiID', 'TaiKhoanID');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'NguoiNhanID', 'TaiKhoanID');
    }
}
