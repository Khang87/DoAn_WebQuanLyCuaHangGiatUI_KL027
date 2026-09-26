<?php

namespace App\Enums;

/**
 * Trạng thái hóa đơn (3 trạng thái chuẩn).
 *
 *   unpaid   -> Chờ thanh toán
 *   partial  -> Thanh toán một phần
 *   paid     -> Đã thanh toán (KHÓA CHỈ ĐỌC - chỉ Owner can thiệp ngoại lệ)
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chờ thanh toán',
            self::Partial => 'Thanh toán một phần',
            self::Paid => 'Đã thanh toán',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Partial => 'bg-info-subtle text-info-emphasis border border-info',
            self::Paid => 'bg-success-subtle text-success-emphasis border border-success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Unpaid => 'hourglass',
            self::Partial => 'circle-half',
            self::Paid => 'check-circle',
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
        return [self::Paid->value, 'completed'];
    }

    /**
     * Giá trị trạng thái này có được coi là đã quyết toán không.
     */
    public static function valueIsPaid(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), self::paidValues(), true);
    }

    public static function parse(mixed $value, self $default = self::Unpaid): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim((string) $value));

        // Map legacy values to paid
        if (in_array($normalized, ['completed'], true)) {
            return self::Paid;
        }

        return self::tryFrom($normalized) ?? $default;
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