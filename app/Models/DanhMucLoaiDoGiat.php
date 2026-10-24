<?php

namespace App\Models;

use App\Support\CatalogCache;
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
        'NgayTao' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(static fn () => CatalogCache::forgetGarmentTypes());
        static::deleted(static fn () => CatalogCache::forgetGarmentTypes());
    }

    public function loaiDoGiats(): HasMany
    {
        return $this->hasMany(LoaiDoGiat::class, 'DanhMucID', 'DanhMucID');
    }
}
