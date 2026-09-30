<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaiKhoanVaiTro extends Model
{
    protected $table = 'TaiKhoan_VaiTro';
    protected $primaryKey = null;
    public $timestamps = false;
    public $incrementing = false;
    public static $snakeAttributes = false;

    protected $fillable = ['TaiKhoanID', 'VaiTroID'];

    protected $casts = [
        'TaiKhoanID' => 'integer',
        'VaiTroID' => 'integer',
    ];

    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'TaiKhoanID');
    }

    public function vaiTro()
    {
        return $this->belongsTo(VaiTro::class, 'VaiTroID');
    }
}