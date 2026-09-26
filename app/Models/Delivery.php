<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'order_id',
        'customer_id',
        'employee_id',
        'method',
        'address',
        'pickup_date',
        'pickup_time',
        'notes',
        'status',
    ];

    protected $casts = [
        'pickup_date' => 'date',
        'pickup_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function getCodeFormatAttribute(): string
    {
        return $this->code ?? ('GH' . str_pad($this->id, 4, '0', STR_PAD_LEFT));
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->method) {
            'nhan_do' => 'Nhận đồ',
            'giao_do' => 'Giao đồ',
            default => 'Giao đồ',
        };
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->type_label;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Chờ xác nhận',
            'picking' => 'Đang lấy đồ',
            'delivering' => 'Đang giao đồ',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => 'Chờ xác nhận',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending' => 'bg-warning-subtle text-warning border-warning',
            'picking' => 'bg-info-subtle text-info border-info',
            'delivering' => 'bg-primary-subtle text-primary border-primary',
            'completed' => 'bg-success-subtle text-success border-success',
            'cancelled' => 'bg-danger-subtle text-danger border-danger',
            default => 'bg-secondary-subtle text-secondary border-secondary',
        };
    }
}