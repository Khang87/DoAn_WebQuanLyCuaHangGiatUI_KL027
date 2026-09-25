<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'order_id', 'customer_id', 'method', 'address', 'pickup_date', 'pickup_time', 'notes', 'status'];

    protected $casts = [
        'pickup_date' => 'date',
        'pickup_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getCodeFormatAttribute(): string
    {
        return $this->code ?? ('GH' . str_pad($this->id, 4, '0', STR_PAD_LEFT));
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->method) {
            'home_pickup', 'pickup', 'nhan_do' => 'Nhận Đồ',
            'dropoff', 'delivery', 'store_delivery', 'giao_do' => 'Giao Đồ',
            default => 'Giao Đồ',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending', 'waiting', 'cho_xac_nhan' => 'Chờ Xác Nhận',
            'picking', 'confirmed', 'dang_lay' => 'Đang Lấy',
            'delivering', 'shipping', 'dang_giao' => 'Đang Giao',
            'completed', 'delivered', 'hoan_thanh' => 'Hoàn Thành',
            'cancelled', 'da_huy' => 'Đã Hủy',
            default => 'Chờ Xác Nhận',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'pending', 'waiting', 'cho_xac_nhan' => 'bg-warning-subtle text-warning border-warning',
            'picking', 'confirmed', 'dang_lay' => 'bg-info-subtle text-info border-info',
            'delivering', 'shipping', 'dang_giao' => 'bg-primary-subtle text-primary border-primary',
            'completed', 'delivered', 'hoan_thanh' => 'bg-success-subtle text-success border-success',
            'cancelled', 'da_huy' => 'bg-danger-subtle text-danger border-danger',
            default => 'bg-secondary-subtle text-secondary border-secondary',
        };
    }
}
