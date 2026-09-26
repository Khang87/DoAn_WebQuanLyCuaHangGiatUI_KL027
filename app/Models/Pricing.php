<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pricing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_id',
        'garment_id',
        'name',
        'unit',
        'price',
        'effective_date',
        'status',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function garment(): BelongsTo
    {
        return $this->belongsTo(Garment::class)->withTrashed();
    }

    /**
     * Bản giá lịch sử mới nhất đang hiệu lực cho cặp service_id + garment_id.
     *
     * Thứ tự ưu tiên: effective_date mới nhất, sau đó tới id mới nhất để
     * các bản ghi trùng ngày vẫn chọn đúng một bản duy nhất.
     */
    public static function getLatestPricing(int $serviceId, int $garmentId): ?self
    {
        return static::where('service_id', $serviceId)
            ->where('garment_id', $garmentId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('effective_date')
                    ->orWhereDate('effective_date', '<=', now());
            })
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    public static function getLatestPrice(int $serviceId, int $garmentId): ?float
    {
        $pricing = static::getLatestPricing($serviceId, $garmentId);

        return $pricing ? (float) $pricing->price : null;
    }

    /**
     * Đơn vị tính tiền có phải "kg" không (không phân biệt hoa thường).
     */
    public static function isWeightUnit(?string $unit): bool
    {
        return in_array(mb_strtolower(trim((string) $unit)), ['kg', 'kgs', 'kilogram'], true);
    }

    /**
     * Danh sách đơn vị tính đang có trong bảng giá (dùng cho dropdown động).
     *
     * @return array<int, string>
     */
    public static function unitOptions(): array
    {
        return static::query()
            ->whereNotNull('unit')
            ->distinct()
            ->orderBy('unit')
            ->pluck('unit')
            ->all();
    }

    public function getStatusLabelAttribute(): string
    {
        return RecordStatus::parse($this->status)->label();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return RecordStatus::parse($this->status)->badgeClass();
    }
}
