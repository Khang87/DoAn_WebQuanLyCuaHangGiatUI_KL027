<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GarmentCondition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['garment_id', 'condition_type', 'description', 'photo', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function garment()
    {
        return $this->belongsTo(Garment::class);
    }
}
