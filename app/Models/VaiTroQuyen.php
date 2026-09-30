<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaiTroQuyen extends Model
{
    protected $table = 'VaiTro_Quyen';
    protected $primaryKey = null;
    public $timestamps = false;
    public $incrementing = false;
    public static $snakeAttributes = false;

    protected $fillable = ['VaiTroID', 'QuyenID'];

    protected $casts = [
        'VaiTroID' => 'integer',
        'QuyenID' => 'integer',
    ];

    public function vaiTro()
    {
        return $this->belongsTo(VaiTro::class, 'VaiTroID');
    }

    public function quyen()
    {
        return $this->belongsTo(Quyen::class, 'QuyenID');
    }
}