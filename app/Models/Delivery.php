<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
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
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
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
        return DeliveryStatus::labelFor($this->status);
    }
}