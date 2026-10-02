<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhachHang extends Model
{
    protected $table = 'KhachHang';

    protected $primaryKey = 'KhachHangID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayTao';

    protected $fillable = [
        'HoTen', 'SoDienThoai', 'Email', 'DiaChi', 'NgayTao', 'TrangThai', 'KhachHangID'];

    protected $casts = [
        'NgayTao' => 'datetime',
    ];

    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'KhachHangID');
    }

    public function diemTichLuy()
    {
        return $this->hasOne(DiemTichLuy::class, 'KhachHangID');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'KhachHangID');
    }

    public function danhGia()
    {
        return $this->hasMany(DanhGia::class, 'KhachHangID');
    }

    public function taiKhoan()
    {
        return $this->hasOne(TaiKhoan::class, 'KhachHangID');
    }

    /**
     * Sổ địa chỉ giao hàng (bảng `khachhang_diachi`).
     */
    public function diaChis()
    {
        return $this->hasMany(KhachHangDiaChi::class, 'khachhangid', 'KhachHangID');
    }

    /* ---------------------------------------------------------------------
     | Alias tiếng Anh cho tầng trên
     |---------------------------------------------------------------------*/

    public function getNameAttribute(): ?string
    {
        return $this->HoTen;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->SoDienThoai;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->DiaChi;
    }

    public function orders()
    {
        return $this->donHangs();
    }

    /**
     * Số điểm tích lũy hiện có của khách (lưu ở bảng `DiemTichLuy`).
     */
    public function points(): int
    {
        $points = $this->diemTichLuy?->DiemHienTai ?? DiemTichLuy::where('KhachHangID', $this->KhachHangID)->value('DiemHienTai');

        return (int) ($points ?? 0);
    }

    public function getPointsAttribute(): int
    {
        return $this->points();
    }

    /**
     * Trừ điểm, không cho âm.
     */
    public function deductPoints(int $points): bool
    {
        if ($points <= 0) {
            return false;
        }

        $record = $this->diemTichLuy ?: DiemTichLuy::firstOrCreate(
            ['KhachHangID' => $this->KhachHangID],
            ['DiemHienTai' => 0, 'NgayCapNhat' => now()]
        );

        if ((int) $record->DiemHienTai < $points) {
            return false;
        }

        $record->update([
            'DiemHienTai' => (int) $record->DiemHienTai - $points,
            'NgayCapNhat' => now(),
        ]);

        $this->unsetRelation('diemTichLuy');

        return true;
    }

    /**
     * Hoàn điểm.
     */
    public function addPoints(int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $record = $this->diemTichLuy ?: DiemTichLuy::firstOrCreate(
            ['KhachHangID' => $this->KhachHangID],
            ['DiemHienTai' => 0, 'NgayCapNhat' => now()]
        );

        $record->update([
            'DiemHienTai' => (int) $record->DiemHienTai + $points,
            'NgayCapNhat' => now(),
        ]);

        $this->unsetRelation('diemTichLuy');
    }
}
