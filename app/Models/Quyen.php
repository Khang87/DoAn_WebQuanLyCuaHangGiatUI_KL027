<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quyen extends Model
{
    protected $table = 'Quyen';

    protected $primaryKey = 'QuyenID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    protected $fillable = ['MaQuyen', 'TenQuyen', 'MoTa', 'TrangThai', 'QuyenID'];

    public function vaiTroQuyens(): HasMany
    {
        return $this->hasMany(VaiTroQuyen::class, 'QuyenID');
    }

    public function vaiTros(): BelongsToMany
    {
        return $this->belongsToMany(
            VaiTro::class,
            'VaiTro_Quyen',
            'QuyenID',
            'VaiTroID',
            'QuyenID',
            'VaiTroID',
        );
    }
}
