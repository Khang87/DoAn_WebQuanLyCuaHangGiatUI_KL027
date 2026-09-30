<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class ThanhToan extends Model
{
    protected $table = 'ThanhToan';

    protected $primaryKey = 'ThanhToanID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'ThoiGian';

    protected $fillable = [
        'DonHangID', 'SoTien', 'PhuongThuc', 'MaGiaoDich', 'ThoiGian', 'TrangThai', 'GhiChu', 'ThanhToanID'];

    protected $casts = [
        'DonHangID' => 'integer',
        'SoTien' => 'float',
        'ThoiGian' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function isSettled(): bool
    {
        return PaymentStatus::parse($this->TrangThai)->isPaid();
    }

    public function isLocked(): bool
    {
        return $this->isSettled() || $this->donHang?->isLocked() === true;
    }

    public function getMethodLabel(): string
    {
        return match ($this->PhuongThuc) {
            'Tiền mặt' => 'Tiền mặt',
            'Chuyển khoản' => 'Chuyển khoản / QR',
            default => (string) $this->PhuongThuc,
        };
    }

    public function getMethodIcon(): string
    {
        return match ($this->PhuongThuc) {
            'Tiền mặt' => 'bi-cash',
            'Chuyển khoản' => 'bi-bank',
            default => 'bi-credit-card',
        };
    }
}
