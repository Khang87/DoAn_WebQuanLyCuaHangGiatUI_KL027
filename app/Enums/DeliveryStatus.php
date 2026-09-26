<?php

namespace App\Enums;

/**
 * Trạng thái giao nhận đồ.
 *
 * Nguồn duy nhất cho module Giao hàng: dropdown trong form chỉnh sửa, dropdown
 * lọc ngoài bảng và badge trong cột Trạng thái. Tập giá trị khớp với
 * DeliveryRequest ('in:pending,picking,delivering,completed,cancelled').
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

        return self::tryFrom(mb_strtolower(trim((string) $value))) ?? $default;
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
