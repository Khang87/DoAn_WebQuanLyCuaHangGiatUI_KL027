<?php

namespace App\Enums;

/**
 * Trạng thái giao nhận đồ.
 *
 * Nguồn duy nhất cho module Giao hàng: dropdown, bộ lọc và badge. Giá trị DB
 * được ánh xạ qua dbValue() theo CHECK của bảng `GiaoNhan`.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Picking = 'picking';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Picking => 'Đang nhận đồ',
            self::Delivering => 'Đang giao đồ',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Picking => 'bg-indigo-subtle text-indigo-emphasis border border-indigo',
            self::Delivering => 'bg-primary-subtle text-primary-emphasis border border-primary',
            self::Completed => 'bg-success-subtle text-success-emphasis border border-success',
            self::Cancelled => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Picking => 'box-open',
            self::Delivering => 'truck',
            self::Completed => 'check-circle',
            self::Cancelled => 'x-circle',
        };
    }

    /**
     * Giá trị ghi vào database (bảng `GiaoNhan`, cột `TrangThai`), khớp với
     * ràng buộc CHECK `GiaoNhan_TrangThai_check`.
     */
    public function dbValue(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thực hiện',
            self::Picking => 'Đang thực hiện',
            self::Delivering => 'Đang thực hiện',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
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

        return match (mb_strtolower(trim((string) $value))) {
            'pending', 'chờ thực hiện' => self::Pending,
            'picking', 'delivering', 'đang thực hiện' => self::Delivering,
            'completed', 'hoàn thành' => self::Completed,
            'cancelled', 'đã hủy' => self::Cancelled,
            default => self::tryFrom(mb_strtolower(trim((string) $value))) ?? $default,
        };
    }

    public static function parseForLeg(mixed $value, string $deliveryType, self $default = self::Pending): self
    {
        if (! $value instanceof self && mb_strtolower(trim((string) $value)) === 'đang thực hiện') {
            return strtoupper($deliveryType) === 'NHAN_DO' ? self::Picking : self::Delivering;
        }

        return self::parse($value, $default);
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
