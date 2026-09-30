<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonViTinh extends Model
{
    protected $table = 'DonViTinh';
    protected $primaryKey = 'DonViTinhID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = ['TenDonViTinh', 'KyHieu', 'TrangThai', 'DonViTinhID'];

    public function bangGias()
    {
        return $this->hasMany(BangGia::class, 'DonViTinhID');
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class, 'DonViTinhID');
    }

    public function getNameAttribute(): ?string
    {
        return $this->TenDonViTinh;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->TrangThai;
    }

    public function getStatusLabelAttribute(): string
    {
        return (string) $this->TrangThai;
    }

    /**
     * Đơn vị tính theo khối lượng (kg) hay theo số lượng (món/chiếc).
     */
    public function isWeightUnit(): bool
    {
        return BangGia::isWeightUnit($this->KyHieu ?? $this->TenDonViTinh);
    }
}