<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'price', 'unit', 'status', 'description', 'service_category_id', 'processing_time', 'icon'];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
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
}
