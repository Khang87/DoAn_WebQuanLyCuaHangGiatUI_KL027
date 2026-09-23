<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['order_id', 'code', 'total', 'status', 'notes'];
    protected $casts = ['total' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
