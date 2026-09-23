<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = ['customer_id', 'method', 'address', 'pickup_date', 'pickup_time', 'status'];
    protected $casts = ['pickup_date' => 'date'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
