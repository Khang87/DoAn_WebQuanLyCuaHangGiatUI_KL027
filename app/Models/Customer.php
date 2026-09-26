<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'points',
        'avatar',
    ];

    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Đơn của khách. KHÔNG nạp bản ghi đã xoá mềm để tổng chi tiêu và số đơn
     * không tính nhầm đơn đã huỷ; dùng ordersWithTrashed() cho màn hình lịch sử.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function ordersWithTrashed(): HasMany
    {
        return $this->hasMany(Order::class)->withTrashed();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->withTrashed();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->withTrashed();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->withTrashed();
    }

    public function deductPoints(int $points): bool
    {
        if ($this->points >= $points) {
            $this->decrement('points', $points);
            return true;
        }
        return false;
    }

    public function addPoints(int $points): void
    {
        $this->increment('points', $points);
    }

    public function getAvatarUrlAttribute(): string
    {
        if (! empty($this->avatar) && file_exists(public_path($this->avatar))) {
            return asset($this->avatar);
        }

        return asset('assets/images/user_' . (($this->id % 8) + 1) . '.jpg');
    }
}