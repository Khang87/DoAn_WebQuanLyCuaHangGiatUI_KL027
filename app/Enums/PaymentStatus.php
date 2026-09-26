<?php

namespace App\Enums;

/**
 * Trạng thái giao dịch thanh toán (5 trạng thái chuẩn).
 *
 *   pending  -> Chờ thanh toán
 *   partial  -> Thanh toán một phần
 *   paid     -> Đã thanh toán
 *   failed   -> Thất bại
 *   refunded -> Đã hoàn tiền
 *
 * Lưu ý: Enum này khớp với InvoiceStatus để hiển thị nhất quán.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::Partial => 'Thanh toán một phần',
            self::Paid => 'Đã thanh toán',
            self::Failed => 'Thất bại',
            self::Refunded => 'Đã hoàn tiền',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Partial => 'bg-info-subtle text-info-emphasis border border-info',
            self::Paid => 'bg-success-subtle text-success-emphasis border border-success',
            self::Failed => 'bg-danger-subtle text-danger-emphasis border border-danger',
            self::Refunded => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass',
            self::Partial => 'circle-half',
            self::Paid => 'check-circle',
            self::Failed => 'times-circle',
            self::Refunded => 'undo',
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

    public static function parse(mixed $value, self $default = self::Pending): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return self::tryFrom($normalized) ?? $default;
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