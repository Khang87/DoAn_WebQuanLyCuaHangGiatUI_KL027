<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Garment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'price',
        'condition_note',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function conditions()
    {
        return $this->hasMany(GarmentCondition::class);
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
     * Icon đại diện cho loại đồ giặt, dùng chung cho view index và show.
     *
     * Không khai báo hàm trong Blade để tránh lỗi "Cannot redeclare function"
     * khi một view được render nhiều lần trong cùng một tiến trình PHP.
     */
    public function icon(): string
    {
        $name = mb_strtolower($this->name ?? '');
        $cat = mb_strtolower($this->category ?? '');

        if (str_contains($name, 'dài') || str_contains($name, 'váy') || str_contains($name, 'đầm') || str_contains($cat, 'truyền thống')) {
            return 'fa-solid fa-person-dress';
        }
        if (str_contains($name, 'khoác') || str_contains($name, 'blazer') || str_contains($name, 'suit') || str_contains($name, 'vest')) {
            return 'fa-solid fa-user-tie';
        }
        if (str_contains($name, 'quần') || str_contains($cat, 'công sở')) {
            return 'fa-solid fa-scissors';
        }
        if (str_contains($name, 'chăn') || str_contains($name, 'mền') || str_contains($name, 'ga') || str_contains($name, 'gối')) {
            return 'fa-solid fa-bed';
        }
        if (str_contains($name, 'giày') || str_contains($name, 'dép') || str_contains($name, 'vớ') || str_contains($name, 'tất')) {
            return 'fa-solid fa-shoe-prints';
        }

        return 'fa-solid fa-shirt';
    }
}
