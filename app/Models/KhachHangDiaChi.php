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
    /**
     * Bảng này được tạo trực tiếp trên Supabase và giữ tên viết thường theo live DDL.
     * Không ép chuyển sang PascalCase vì PostgreSQL sẽ giữ tên nguyên nếu được quoted.
     */
    protected $table = 'khachhang_diachi';

    protected $primaryKey = 'diachiid';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    /** Giữ nguyên tên cột viết thường khi sinh SQL. */
    public static $snakeAttributes = false;

    /** Cột thời gian thực tế của bảng. */
    public const CREATED_AT = 'ngaytao';

    public const UPDATED_AT = null;

    protected $fillable = [
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
