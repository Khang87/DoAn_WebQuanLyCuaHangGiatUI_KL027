<?php

namespace App\Models;

use App\Support\CatalogCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoaiDoGiat extends Model
{
    use HasFactory;

    protected $table = 'LoaiDoGiat';

    protected $primaryKey = 'LoaiDoGiatID';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = ['TenLoaiDoGiat', 'MoTa', 'TrangThai'];

    protected $casts = [
        'LoaiDoGiatID' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(static fn () => CatalogCache::forgetGarmentTypes());
        static::deleted(static fn () => CatalogCache::forgetGarmentTypes());
    }

    public function chiTietDonHangs(): HasMany
    {
        return $this->hasMany(ChiTietDonHang::class, 'LoaiDoGiatID', 'LoaiDoGiatID');
    }

    public function bangGias(): HasMany
    {
        return $this->hasMany(BangGia::class, 'LoaiDoGiatID', 'LoaiDoGiatID');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'LoaiDoGiatID', 'LoaiDoGiatID');
    }

    public function getIdAttribute(): ?int
    {
        return $this->LoaiDoGiatID === null ? null : (int) $this->LoaiDoGiatID;
    }

    public function getNameAttribute(): ?string
    {
        return $this->TenLoaiDoGiat;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->TrangThai;
    }

    public function getStatusLabelAttribute(): string
    {
        return (string) $this->TrangThai;
    }
}
