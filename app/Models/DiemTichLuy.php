<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiemTichLuy extends Model
{
    protected $table = 'DiemTichLuy';
    protected $primaryKey = 'DiemTichLuyID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayCapNhat';

    protected $fillable = ['KhachHangID', 'DiemHienTai', 'NgayCapNhat', 'DiemTichLuyID'];

    protected $casts = [
        'KhachHangID' => 'integer',
        'DiemHienTai' => 'integer',
        'NgayCapNhat' => 'datetime',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }
}