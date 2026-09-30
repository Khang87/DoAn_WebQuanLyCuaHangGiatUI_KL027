<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BangGia extends Model
{
    protected $table = 'BangGia';
    protected $primaryKey = 'BangGiaID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    protected $fillable = [
        'DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'DonGia', 'NgayApDung', 'NgayKetThuc', 'TrangThai', 'BangGiaID'];

    protected $casts = [
        'DichVuID' => 'integer',
        'LoaiDoGiatID' => 'integer',
        'DonViTinhID' => 'integer',
        'DonGia' => 'float',
        'NgayApDung' => 'date',
        'NgayKetThuc' => 'date',
    ];

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

    public function service()
    {
        return $this->dichVu();
    }

    public function garment()
    {
        return $this->loaiDoGiat();
    }

    public function getPriceAttribute(): ?float
    {
        return $this->DonGia;
    }

    public function getEffectiveDateAttribute(): mixed
    {
        return $this->NgayApDung;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->TrangThai;
    }

    public function getUnitAttribute(): ?string
    {
        return $this->donViTinh?->KyHieu ?? $this->donViTinh?->TenDonViTinh;
    }

    /**
     * Bản giá lịch sử mới nhất đang hiệu lực cho cặp dịch vụ + loại đồ giặt.
     *
     * Thứ tự ưu tiên: NgayApDung mới nhất, sau đó tới BangGiaID mới nhất để
     * các bản ghi trùng ngày vẫn chọn đúng một bản duy nhất.
     */
    public static function getLatestPricing(int $serviceId, int $garmentId, ?int $unitId = null): ?self
    {
        return static::where('DichVuID', $serviceId)
            ->where('LoaiDoGiatID', $garmentId)
            ->when($unitId !== null, fn ($query) => $query->where('DonViTinhID', $unitId))
            ->where('TrangThai', 'Hoạt động')
            ->where(function ($query) {
                $query->whereNull('NgayApDung')
                    ->orWhereDate('NgayApDung', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('NgayKetThuc')
                    ->orWhereDate('NgayKetThuc', '>=', now());
            })
            ->orderByDesc('NgayApDung')
            ->orderByDesc('BangGiaID')
            ->first();
    }

    public static function getLatestPrice(int $serviceId, int $garmentId, ?int $unitId = null): ?float
    {
        $pricing = static::getLatestPricing($serviceId, $garmentId, $unitId);

        return $pricing ? (float) $pricing->DonGia : null;
    }

    /**
     * Đơn vị tính có phải "kg" không (không phân biệt hoa thường).
     */
    public static function isWeightUnit(mixed $unit): bool
    {
        if (is_object($unit)) {
            $unit = $unit->KyHieu ?? $unit->TenDonViTinh ?? null;
        }

        return in_array(mb_strtolower(trim((string) $unit)), ['kg', 'kgs', 'kilogram'], true);
    }

    /**
     * Danh sách đơn vị tính đang có (dùng cho dropdown động).
     *
     * @return array<int|string, string>
     */
    public static function unitOptions(): array
    {
        return DonViTinh::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('TenDonViTinh')
            ->pluck('TenDonViTinh', 'DonViTinhID')
            ->all();
    }

    public function getStatusLabelAttribute(): string
    {
        return (string) $this->TrangThai;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->TrangThai) {
            'Hoạt động' => 'bg-success-subtle text-success-emphasis border border-success',
            'Tạm ngưng' => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
            default => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }
}
