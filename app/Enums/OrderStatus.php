<?php

namespace App\Enums;

/**
 * Trạng thái vận hành của đơn giặt ủi.
 *
 * Đây là nguồn duy nhất cho danh sách trạng thái đơn: dùng cho form quản trị,
 * dropdown lọc, nhãn tiếng Việt và cho Order::isLocked().
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Sorting = 'sorting';
    case Processing = 'processing';
    case Washed = 'washed';
    case Delivering = 'delivering';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ tiếp nhận',
            self::Received => 'Đã nhận đồ',
            self::Sorting => 'Đang phân loại',
            self::Processing => 'Đang giặt / Xử lý',
            self::Washed => 'Đã giặt xong',
            self::Delivering => 'Đang giao đồ',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Trạng thái đã quyết toán: tiền đã chốt nên đơn chỉ được xem.
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

        return self::tryFrom(mb_strtolower(trim((string) $value))) ?? $default;
    }
}
