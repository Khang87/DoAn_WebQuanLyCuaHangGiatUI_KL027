<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quyen extends Model
{
    protected $table = 'Quyen';
    protected $primaryKey = 'QuyenID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = ['MaQuyen', 'TenQuyen', 'MoTa', 'TrangThai', 'QuyenID'];

    public function vaiTroQuyens()
    {
        return $this->hasMany(VaiTroQuyen::class, 'QuyenID');
    }
}