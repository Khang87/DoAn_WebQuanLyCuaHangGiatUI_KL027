<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sổ địa chỉ giao hàng của khách hàng.
 *
 * Bảng `khachhang_diachi` (snake_case) được tạo ngoài bộ migration chuẩn nên
 * không có `created_at`/`updated_at`; cột thời gian thật là `ngaytao`.
 */
class KhachHangDiaChi extends Model
{
    protected $table = 'khachhang_diachi';

    protected $primaryKey = 'diachiid';

    public $timestamps = false;

    /** Giữ nguyên tên cột viết thường khi sinh SQL. */
    public static $snakeAttributes = false;

    /** Cột thời gian thực tế của bảng. */
    public const CREATED_AT = 'ngaytao';

    protected $fillable = [
        'diachiid',
        'khachhangid',
        'tennguoinhan',
        'sodienthoai',
        'diachi',
        'ghichu',
        'macdinh',
        'ngaytao',
    ];

    protected $casts = [
        'diachiid' => 'integer',
        'khachhangid' => 'integer',
        'macdinh' => 'boolean',
        'ngaytao' => 'datetime',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'khachhangid');
    }

    /**
     * Địa chỉ mặc định của khách hàng.
     */
    public function scopeMacDinh($query)
    {
        return $query->where('macdinh', true);
    }
}
