<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'staff_id',
        'method',
        'scheduled_date',
        'scheduled_time',
        'notes',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function getMethodLabelAttribute(): string
    {
        return match($this->method) {
            'nhan_do' => 'Nhận đồ',
            'giao_do' => 'Giao đồ',
            default => 'Nhận đồ',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'arrived' => 'Đã đến nơi',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => 'Chờ xác nhận',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending' => 'bg-warning-subtle text-warning border-warning',
            'confirmed' => 'bg-info-subtle text-info border-info',
            'arrived' => 'bg-primary-subtle text-primary border-primary',
            'completed' => 'bg-success-subtle text-success border-success',
            'cancelled' => 'bg-danger-subtle text-danger border-danger',
            default => 'bg-secondary-subtle text-secondary border-secondary',
        };
    }
}