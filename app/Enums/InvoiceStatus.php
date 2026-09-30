<?php

namespace App\Enums;

/**
 * Trạng thái hóa đơn theo ràng buộc của bảng `HoaDon`.
 *
 *   Chưa thanh toán
 *   Đã thanh toán
 *   Đã hủy
 */
enum InvoiceStatus: string
{
    case Unpaid = 'Chưa thanh toán';
    case Paid = 'Đã thanh toán';
    case Cancelled = 'Đã hủy';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::Paid => 'Đã thanh toán',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Paid => 'bg-success-subtle text-success-emphasis border border-success',
            self::Cancelled => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Unpaid => 'hourglass',
            self::Paid => 'check-circle',
            self::Cancelled => 'times-circle',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
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

    /**
     * Mọi giá trị được coi là "đã quyết toán" (kể cả giá trị legacy).
     *
     * @return array<int, string>
     */
    public static function paidValues(): array
    {
        return [self::Paid->value];
    }

    /**
     * Giá trị trạng thái này có được coi là đã quyết toán không.
     */
    public static function valueIsPaid(mixed $value): bool
    {
        return self::parse($value)->isPaid();
    }

    public static function parse(mixed $value, self $default = self::Unpaid): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return match (mb_strtolower(trim((string) $value))) {
            'unpaid', 'pending', 'chưa thanh toán' => self::Unpaid,
            'paid', 'completed', 'đã thanh toán' => self::Paid,
            'cancelled', 'đã hủy' => self::Cancelled,
            default => self::tryFrom((string) $value) ?? $default,
        };
    }

    public static function labelFor(mixed $value, self $default = self::Unpaid): string
    {
        return self::parse($value, $default)->label();
    }

    public static function badgeClassFor(mixed $value, self $default = self::Unpaid): string
    {
        return self::parse($value, $default)->badgeClass();
    }

    public static function iconFor(mixed $value, self $default = self::Unpaid): string
    {
        return self::parse($value, $default)->icon();
    }
}
