<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['code', 'customer_id', 'service_id', 'weight_kg', 'quantity_items', 'total_amount', 'status', 'notes'];

    protected $casts = ['total_amount' => 'decimal:2'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
