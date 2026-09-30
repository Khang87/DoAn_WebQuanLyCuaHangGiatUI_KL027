<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoaiDichVu extends Model
{
    protected $table = 'LoaiDichVu';
    protected $primaryKey = 'LoaiDichVuID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = ['TenLoaiDichVu', 'MoTa', 'TrangThai', 'LoaiDichVuID'];

    public function dichVus()
    {
        return $this->hasMany(DichVu::class, 'LoaiDichVuID');
    }
}