<?php

namespace App\Enums;

/**
 * Trạng thái hóa đơn.
 *
 * Giá trị thật đang dùng trong dự án (InvoiceService::updateStatus và các seeder):
 *   - "unpaid"  : chờ thanh toán
 *   - "partial" : thanh toán một phần
 *   - "paid"    : đã thanh toán
 *
 * "completed" là giá trị legacy vẫn còn trong một số dòng dữ liệu cũ và được
 * hiểu là đã quyết toán nên vẫn được coi là đã thanh toán.
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
            self::Paid => 'bg-success-subtle text-success border-success',
            self::Partial => 'bg-warning-subtle text-warning border-warning',
            self::Unpaid => 'bg-danger-subtle text-danger border-danger',
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

        if ($normalized === 'completed') {
            return self::Paid;
        }

        return self::tryFrom($normalized) ?? $default;
    }
}
