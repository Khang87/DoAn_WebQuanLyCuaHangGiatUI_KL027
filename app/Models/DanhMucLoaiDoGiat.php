<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DanhMucLoaiDoGiat extends Model
{
    protected $table = 'DanhMucLoaiDoGiat';

    protected $primaryKey = 'DanhMucID';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = ['TenDanhMuc', 'MoTa', 'TrangThai'];

    protected $casts = [
        'DanhMucID' => 'integer',
    ];

    public function loaiDoGiats(): HasMany
    {
        return $this->hasMany(LoaiDoGiat::class, 'DanhMucID', 'DanhMucID');
    }
}
