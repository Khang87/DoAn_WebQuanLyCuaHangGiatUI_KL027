<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'price',
        'unit',
        'status',
        'description',
        'service_category_id',
        'processing_time',
        'icon',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function pricings(): HasMany
    {
        return $this->hasMany(Pricing::class);
    }

    public function getFormattedProcessingTimeAttribute(): string
    {
        $hours = $this->processing_time ?? 0;
        if ($hours < 24) {
            return "{$hours} giờ";
        }
        $days = floor($hours / 24);
        $remainHours = $hours % 24;
        return $remainHours > 0 ? "{$days} ngày {$remainHours} giờ" : "{$days} ngày";
    }

    public function getStatusLabelAttribute(): string
    {
        return RecordStatus::parse($this->status)->label();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return RecordStatus::parse($this->status)->badgeClass();
    }

    /**
     * Icon + màu nền đại diện cho dịch vụ, dùng chung cho view index và show.
     *
     * @return array{icon: string, bg: string}
     */
    public function iconConfig(): array
    {
        $name = mb_strtolower($this->name ?? '');
        $type = mb_strtolower($this->type ?? '');

        if (str_contains($name, 'khô') || str_contains($type, 'khô') || str_contains($name, 'hấp') || str_contains($type, 'dry')) {
            return ['icon' => 'fa-solid fa-shirt', 'bg' => 'bg-info'];
        }
        if (str_contains($name, 'ủi') || str_contains($type, 'ủi') || str_contains($type, 'iron')) {
            return ['icon' => 'fa-solid fa-jug-detergent', 'bg' => 'bg-warning text-dark'];
        }
        if (str_contains($name, 'chăn') || str_contains($name, 'mền') || str_contains($name, 'ga') || str_contains($name, 'thảm') || str_contains($type, 'chăn')) {
            return ['icon' => 'fa-solid fa-bed', 'bg' => 'bg-danger'];
        }
        if (str_contains($name, 'giày') || str_contains($type, 'giày') || str_contains($name, 'dép')) {
            return ['icon' => 'fa-solid fa-shoe-prints', 'bg' => 'bg-dark'];
        }
        if (str_contains($name, 'nhanh') || str_contains($name, 'tốc')) {
            return ['icon' => 'fa-solid fa-bolt', 'bg' => 'bg-secondary'];
        }

        return ['icon' => 'fa-solid fa-droplet', 'bg' => 'bg-primary'];
    }
}