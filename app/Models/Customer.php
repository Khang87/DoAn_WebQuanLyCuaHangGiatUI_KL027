<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'email', 'phone', 'address', 'points', 'type'];

    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function recalculateLoyaltyLevel(): string
    {
        if ($this->points >= 2000) {
            return 'VIP';
        } elseif ($this->points >= 500) {
            return 'Thường';
        }
        return 'Mới';
    }

    public function getLoyaltyLevelAttribute(): string
    {
        return $this->recalculateLoyaltyLevel();
    }

    protected function setPointsAttribute($value)
    {
        $this->attributes['points'] = (int) $value;
        $this->attributes['type'] = $this->recalculateLoyaltyLevel();
    }
}
