<?php

namespace App\Enums;

/**
 * Trạng thái lịch hẹn nhận/giao đồ.
 *
 * Giá trị lưu trong database (bảng `Booking`, cột `TrangThai`) là tiếng Việt
 * không dấu, khớp với ràng buộc CHECK `booking_trangthai_check`:
 *
 *   ChoTiepNhan -> DaXacNhan -> HoanThanh
 *   DaHuy
 *
 * Quy trình nghiệp vụ:
 *   ChoTiepNhan -> Chờ xác nhận
 *   DaXacNhan   -> Đã xác nhận (tự động tạo DonHang ở trạng thái Chờ tiếp nhận)
 *   HoanThanh   -> Hoàn thành
 *   DaHuy       -> Đã hủy
 */
enum BookingStatus: string
{
    case Pending = 'ChoTiepNhan';
    case Confirmed = 'DaXacNhan';
    case Completed = 'HoanThanh';
    case Cancelled = 'DaHuy';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Confirmed => 'bg-success-subtle text-success-emphasis border border-success',
            self::Completed => 'bg-primary-subtle text-primary-emphasis border border-primary',
            self::Cancelled => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Confirmed => 'check-circle',
            self::Completed => 'check2-all',
            self::Cancelled => 'x-circle',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function parse(mixed $value, self $default = self::Pending): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim((string) $value));

        // Map giá trị cũ (trước khi đổi sang tiếng Việt không dấu)
        return match ($normalized) {
            'pending', 'chờ xác nhận' => self::Pending,
            'confirmed', 'arrived', 'đã xác nhận' => self::Confirmed,
            'completed', 'hoàn thành' => self::Completed,
            'cancelled', 'đã hủy' => self::Cancelled,
            default => self::tryFrom((string) $value) ?? $default,
        };
    }

    public static function labelFor(mixed $value, self $default = self::Pending): string
    {
        return self::parse($value, $default)->label();
    }

    public static function badgeClassFor(mixed $value, self $default = self::Pending): string
    {
        return self::parse($value, $default)->badgeClass();
    }

    public static function iconFor(mixed $value, self $default = self::Pending): string
    {
        return self::parse($value, $default)->icon();
    }
}
