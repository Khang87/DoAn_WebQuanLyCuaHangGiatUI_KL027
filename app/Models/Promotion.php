<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = ['name', 'code', 'discount', 'expires_at', 'status'];
    protected $casts = ['expires_at' => 'date'];
}
