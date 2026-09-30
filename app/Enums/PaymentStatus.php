<?php

namespace App\Enums;

/**
 * Trạng thái giao dịch thanh toán theo ràng buộc của bảng `ThanhToan`.
 *
 *   Chờ thanh toán, Thành công, Thất bại, Đã hoàn tiền
 */
enum PaymentStatus: string
{
    case Pending = 'Chờ thanh toán';
    case Paid = 'Thành công';
    case Failed = 'Thất bại';
    case Refunded = 'Đã hoàn tiền';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::Paid => 'Đã thanh toán',
            self::Failed => 'Thất bại',
            self::Refunded => 'Đã hoàn tiền',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Paid => 'bg-success-subtle text-success-emphasis border border-success',
            self::Failed => 'bg-danger-subtle text-danger-emphasis border border-danger',
            self::Refunded => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'hourglass',
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

        return match (mb_strtolower(trim((string) $value))) {
            'pending', 'chờ thanh toán' => self::Pending,
            'paid', 'success', 'thành công' => self::Paid,
            'failed', 'thất bại' => self::Failed,
            'refunded', 'đã hoàn tiền' => self::Refunded,
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
