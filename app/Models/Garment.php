<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Garment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'price',
        'condition_note',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
