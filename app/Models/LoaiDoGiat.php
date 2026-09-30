<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoaiDoGiat extends Model
{
    protected $table = 'LoaiDoGiat';
    protected $primaryKey = 'LoaiDoGiatID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = ['TenLoaiDoGiat', 'MoTa', 'TrangThai', 'LoaiDoGiatID'];

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class, 'LoaiDoGiatID');
    }

    public function bangGias()
    {
        return $this->hasMany(BangGia::class, 'LoaiDoGiatID');
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