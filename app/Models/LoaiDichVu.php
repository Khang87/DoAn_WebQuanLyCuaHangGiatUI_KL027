<?php

namespace App\Models;

use App\Support\CatalogCache;
use Illuminate\Database\Eloquent\Model;

class LoaiDichVu extends Model
{
    protected $table = 'LoaiDichVu';

    protected $primaryKey = 'LoaiDichVuID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = ['TenLoaiDichVu', 'MoTa', 'TrangThai', 'LoaiDichVuID'];

    protected static function booted(): void
    {
        static::saved(static fn () => CatalogCache::forgetServiceCategories());
        static::deleted(static fn () => CatalogCache::forgetServiceCategories());
    }

    public function dichVus()
    {
        return $this->hasMany(DichVu::class, 'LoaiDichVuID');
    }
}
