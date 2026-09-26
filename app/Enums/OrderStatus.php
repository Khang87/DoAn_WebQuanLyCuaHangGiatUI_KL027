<?php

namespace App\Enums;

/**
 * Trạng thái vận hành của đơn giặt ủi (5 trạng thái chuẩn).
 *
 * Quy trình:
 *   pending            -> Chờ xử lý (Tạo mới hoặc auto-insert từ Đặt lịch)
 *   processing         -> Đang xử lý (Giặt / Sấy / Í)
 *   ready_for_pickup   -> Chờ giao / Chờ thanh toán (Đã giặt xong, chờ khách lấy)
 *   completed          -> Đã hoàn thành (Giao đồ & thu tiền xong) - KHÓA HOÀN TOÀN
 *   cancelled          -> Đã hủy
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::Processing => 'Đang xử lý',
            self::ReadyForPickup => 'Chờ giao / Chờ thanh toán',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Processing => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-semibold',
            self::ReadyForPickup => 'bg-info-subtle text-info-emphasis border border-info',
            self::Completed => 'bg-success-subtle text-success-emphasis border border-success',
            self::Cancelled => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Processing => 'arrow-repeat',
            self::ReadyForPickup => 'box-seam',
            self::Completed => 'check-circle',
            self::Cancelled => 'x-circle',
        };
    }

    /**
     * Trạng thái đã quyết toán: tiền đã chốt nên đơn chỉ được xem.
     * Chỉ 'completed' là đã quyết toán.
     */
    public function isSettled(): bool
    {
        return $this === self::Completed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function settledValues(): array
    {
        return array_values(array_map(
            fn (self $case) => $case->value,
            array_filter(self::cases(), fn (self $case) => $case->isSettled())
        ));
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

        $normalized = mb_strtolower(trim((string) $value));

        // Map legacy values to new enum
        return match ($normalized) {
            'received', 'sorting', 'washed', 'delivering' => self::Processing,
            default => self::tryFrom($normalized) ?? $default,
        };
    }

    /**
     * Nhãn tiếng Việt cho một giá trị trạng thái bất kỳ, không ném lỗi.
     */
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