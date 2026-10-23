<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingYeuCauDichVu extends Model
{
    protected $table = 'BookingYeuCauDichVu';

    protected $primaryKey = 'BookingYeuCauDichVuID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    public function dichVu(): BelongsTo
    {
        return $this->belongsTo(DichVu::class, 'DichVuID', 'DichVuID');
    }

    public function loaiDoGiat(): BelongsTo
    {
        return $this->belongsTo(LoaiDoGiat::class, 'LoaiDoGiatID', 'LoaiDoGiatID');
    }

    public function donViTinh(): BelongsTo
    {
        return $this->belongsTo(DonViTinh::class, 'DonViTinhID', 'DonViTinhID');
    }
}
