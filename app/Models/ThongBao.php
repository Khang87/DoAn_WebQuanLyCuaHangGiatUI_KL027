<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThongBao extends Model
{
    protected $table = 'ThongBao';
    protected $primaryKey = 'ThongBaoID';
    public $timestamps = false;
    public static $snakeAttributes = false;

    /**
     * Bảng không có `created_at`/`updated_at`; trỏ tên cột thời gian về cột thật
     * để các scope mặc định của Eloquent (`latest()`, `oldest()`) hoạt động.
     */
    public const CREATED_AT = 'ThoiGianGui';

    protected $fillable = [
        'TaiKhoanID', 'DonHangID', 'LoaiThongBao', 'TieuDe', 'NoiDung',
        'ThoiGianGui', 'DaDoc', 'ThongBaoID'];

    protected $casts = [
        'TaiKhoanID' => 'integer',
        'DonHangID' => 'integer',
        'ThoiGianGui' => 'datetime',
        'DaDoc' => 'boolean',
    ];

    /**
     * Khoá chính dưới tên `id` cho route/view dùng chung với bảng `notifications`.
     */
    public function getIdAttribute(): int
    {
        return (int) $this->attributes['ThongBaoID'];
    }

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['TieuDe'] ?? null;
    }

    /**
     * Đã đọc thì trả thời điểm gửi, chưa đọc thì `null` (khớp `read_at` của Laravel).
     */
    public function getReadAtAttribute(): mixed
    {
        return $this->DaDoc ? $this->ThoiGianGui : null;
    }

    public function getBodyAttribute(): ?string
    {
        return $this->attributes['NoiDung'] ?? null;
    }

    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'TaiKhoanID');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }
}