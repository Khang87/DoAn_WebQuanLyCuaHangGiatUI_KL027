<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'email', 'phone', 'address', 'points', 'type'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
