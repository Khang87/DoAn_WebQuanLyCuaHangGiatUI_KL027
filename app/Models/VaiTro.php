<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaiTro extends Model
{
    protected $table = 'VaiTro';
    protected $primaryKey = 'VaiTroID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = ['TenVaiTro', 'MoTa', 'TrangThai', 'VaiTroID'];

    public function taiKhoanVaiTros()
    {
        return $this->hasMany(TaiKhoanVaiTro::class, 'VaiTroID');
    }

    public function vaiTroQuyens()
    {
        return $this->hasMany(VaiTroQuyen::class, 'VaiTroID');
    }
}