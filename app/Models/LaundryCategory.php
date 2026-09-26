<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'slug', 'description', 'icon', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'laundry_category_id');
    }

    public function garments()
    {
        return $this->hasMany(Garment::class, 'laundry_category_id');
    }
}