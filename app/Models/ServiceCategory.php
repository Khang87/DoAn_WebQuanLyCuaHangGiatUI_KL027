<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends LoaiDichVu
{
    public function services(): HasMany
    {
        return $this->hasMany(DichVu::class, 'LoaiDichVuID', 'LoaiDichVuID');
    }
}
