<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    public function getThoiGianAttribute(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Mobile RPC payments use the database UTC default; web payments are written in Vietnam time.
        $storageTimezone = str_starts_with((string) ($this->attributes['MaGiaoDich'] ?? ''), 'RPC-')
            ? 'UTC'
            : config('app.timezone');

        return Carbon::parse($value, $storageTimezone)->setTimezone(config('app.timezone'));
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'DonHangID');
    }

    public function isSettled(): bool
    {
        return PaymentStatus::parse($this->TrangThai)->isPaid();
    }

    public function isFinanciallyFinal(): bool
    {
        return PaymentStatus::parse($this->TrangThai)->isFinanciallyFinal();
    }

    public function isLocked(): bool
    {
        return $this->isFinanciallyFinal() || $this->donHang?->isLocked() === true;
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
