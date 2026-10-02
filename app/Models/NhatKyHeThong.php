<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NhatKyHeThong extends Model
{
    protected $table = 'NhatKyHeThong';

    protected $primaryKey = 'NhatKyID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'TaiKhoanID',
        'HanhDong',
        'BangDuLieu',
        'BanGhiID',
        'DuLieuCu',
        'DuLieuMoi',
        'LyDo',
        'ThoiGian',
        'IPAddress',
        'UserAgent',
    ];

    protected $casts = [
        'NhatKyID' => 'integer',
        'TaiKhoanID' => 'integer',
        'BanGhiID' => 'integer',
        'DuLieuCu' => 'array',
        'DuLieuMoi' => 'array',
        'ThoiGian' => 'datetime',
    ];

    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'TaiKhoanID', 'TaiKhoanID');
    }
}
