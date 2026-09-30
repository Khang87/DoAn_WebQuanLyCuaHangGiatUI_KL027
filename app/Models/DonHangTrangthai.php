<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lịch sử thay đổi trạng thái đơn hàng.
 *
 * Bảng `donhang_trangthai` (snake_case) được tạo ngoài bộ migration chuẩn nên
 * không có cặp `created_at`/`updated_at`; cột thời gian thật là `thoigian`.
 */
class DonHangTrangthai extends Model
{
    protected $table = 'donhang_trangthai';

    protected $primaryKey = 'donhang_trangthaiid';

    public $timestamps = false;

    /** Giữ nguyên tên cột viết thường khi sinh SQL. */
    public static $snakeAttributes = false;

    /** Cột thời gian thực tế của bảng. */
    public const CREATED_AT = 'thoigian';

    protected $fillable = [
        'donhang_trangthaiid',
        'donhangid',
        'taikhoanid',
        'trangthaicu',
        'trangthaimoi',
        'lydo',
        'thoigian',
    ];

    protected $casts = [
        'donhang_trangthaiid' => 'integer',
        'donhangid' => 'integer',
        'taikhoanid' => 'integer',
        'thoigian' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'donhangid');
    }

    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'taikhoanid');
    }
}
