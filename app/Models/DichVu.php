<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DichVu extends Model
{
    protected $table = 'DichVu';

    protected $primaryKey = 'DichVuID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayTao';

    protected $fillable = [
        'LoaiDichVuID', 'TenDichVu', 'MoTa', 'ThoiGianDuKien', 'TrangThai', 'NgayTao', 'DichVuID'];

    protected $casts = [
        'LoaiDichVuID' => 'integer',
        'ThoiGianDuKien' => 'integer',
        'NgayTao' => 'datetime',
    ];

    public function loaiDichVu()
    {
        return $this->belongsTo(LoaiDichVu::class, 'LoaiDichVuID');
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class, 'DichVuID');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'DichVuID');
    }

    public function bangGias()
    {
        return $this->hasMany(BangGia::class, 'DichVuID');
    }

    public function category()
    {
        return $this->loaiDichVu();
    }

    /**
     * Tên dịch vụ hiển thị (tương đương cột `name` cũ).
     */
    public function getNameAttribute(): ?string
    {
        return $this->TenDichVu;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->TrangThai;
    }

    public function getStatusLabelAttribute(): string
    {
        return (string) $this->TrangThai;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->TrangThai) {
            'Hoạt động' => 'bg-success-subtle text-success-emphasis border border-success',
            default => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
        };
    }
}
