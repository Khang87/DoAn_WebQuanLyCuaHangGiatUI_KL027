<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietDonHang extends Model
{
    protected $table = 'ChiTietDonHang';

    protected $primaryKey = 'ChiTietDonHangID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = [
        'DonHangID', 'DichVuID', 'LoaiDoGiatID', 'DonViTinhID',
        'SoLuong', 'KhoiLuong', 'DonGia', 'ThanhTien', 'GhiChu',
        'TinhTrangTruocKhiGiat', 'ChiTietDonHangID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'DichVuID' => 'integer',
        'LoaiDoGiatID' => 'integer',
        'DonViTinhID' => 'integer',
        'SoLuong' => 'float',
        'KhoiLuong' => 'float',
        'DonGia' => 'float',
        'ThanhTien' => 'float',
        'TinhTrangTruocKhiGiat' => 'string',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function dichVu()
    {
        return $this->belongsTo(DichVu::class, 'DichVuID');
    }

    public function loaiDoGiat()
    {
        return $this->belongsTo(LoaiDoGiat::class, 'LoaiDoGiatID');
    }

    public function donViTinh()
    {
        return $this->belongsTo(DonViTinh::class, 'DonViTinhID');
    }

    /* ---------------------------------------------------------------------
     | Alias tiếng Anh cho tầng trên
     |---------------------------------------------------------------------*/

    public function order()
    {
        return $this->donHang();
    }

    public function service()
    {
        return $this->dichVu();
    }

    public function garment()
    {
        return $this->loaiDoGiat();
    }

    public function getItemNameAttribute(): string
    {
        $parts = array_filter([
            $this->dichVu?->TenDichVu,
            $this->loaiDoGiat?->TenLoaiDoGiat,
        ]);

        return $parts !== [] ? implode(' - ', $parts) : 'Mặt hàng';
    }

    public function getItemTypeAttribute(): string
    {
        return 'service';
    }

    public function getPriceAttribute(): ?float
    {
        return $this->DonGia;
    }

    public function getQuantityAttribute(): ?float
    {
        return $this->SoLuong;
    }

    public function getWeightAttribute(): ?float
    {
        return $this->KhoiLuong;
    }

    public function getSubtotalAttribute(): ?float
    {
        return $this->ThanhTien;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->GhiChu;
    }

    public function getUnitAttribute(): ?string
    {
        return $this->donViTinh?->KyHieu ?? $this->donViTinh?->TenDonViTinh;
    }
}
