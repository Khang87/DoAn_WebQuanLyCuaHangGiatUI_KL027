<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'rating',
        'content',
        'images',
        'shop_response',
        'status',
        'reviewed_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'images' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'visible' => 'Hiển thị',
            'hidden' => 'Ẩn',
            default => 'Hiển thị',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'visible' => 'bg-success-subtle text-success border-success',
            'hidden' => 'bg-secondary-subtle text-secondary border-secondary',
            default => 'bg-secondary-subtle text-secondary border-secondary',
        };
    }
}