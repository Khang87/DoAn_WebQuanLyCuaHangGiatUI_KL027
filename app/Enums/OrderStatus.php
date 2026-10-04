<?php

namespace App\Enums;

/**
 * Trạng thái vận hành của đơn giặt ủi.
 *
 * Giá trị lưu trong database (bảng `DonHang`, cột `TrangThai`) là tiếng Việt,
 * khớp với ràng buộc CHECK `CK_DonHang_TrangThai`:
 *
 *   Chờ tiếp nhận -> Đã tiếp nhận -> Đang giặt -> Hoàn thành giặt
 *                  -> Đang giao -> Đã giao -> Đã thanh toán
 *   Đã hủy
 */
enum OrderStatus: string
{
    case Pending = 'Chờ tiếp nhận';
    case Received = 'Đã tiếp nhận';
    case Washing = 'Đang giặt';
    case Washed = 'Hoàn thành giặt';
    case Delivering = 'Đang giao';
    case Delivered = 'Đã giao';
    case Paid = 'Đã thanh toán';
    case Cancelled = 'Đã hủy';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ tiếp nhận',
            self::Received => 'Đã tiếp nhận',
            self::Washing => 'Đang giặt',
            self::Washed => 'Hoàn thành giặt',
            self::Delivering => 'Đang giao',
            self::Delivered => 'Đã giao',
            self::Paid => 'Đã thanh toán',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning-emphasis border border-warning',
            self::Received, self::Washing => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-semibold',
            self::Washed, self::Delivering => 'bg-info-subtle text-info-emphasis border border-info',
            self::Delivered => 'bg-success-subtle text-success-emphasis border border-success',
            self::Paid => 'bg-success-subtle text-success-emphasis border border-success',
            self::Cancelled => 'bg-danger-subtle text-danger-emphasis border border-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Received, self::Washing => 'arrow-repeat',
            self::Washed, self::Delivering => 'box-seam',
            self::Delivered, self::Paid => 'check-circle',
            self::Cancelled => 'x-circle',
        };
    }

    /**
     * Trạng thái đã quyết toán: tiền đã chốt nên đơn chỉ được xem.
     */
    public function isSettled(): bool
    {
        return $this === self::Paid;
    }

    public function isCompletedMilestone(): bool
    {
        return in_array($this, [
            self::Received,
            self::Washed,
            self::Delivered,
            self::Paid,
        ], true);
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

    /**
     * Chuyển giá trị bất kỳ về enum.
     *
     * Ngoài giá trị tiếng Việt của database, hàm vẫn chấp nhận các mã tiếng Anh
     * của phiên bản cũ để dữ liệu cũ / form cũ không bị vỡ.
     */
    public static function parse(mixed $value, self $default = self::Pending): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return match ($normalized) {
            'received' => self::Received,
            'sorting', 'washing', 'washed', 'processing' => self::Washing,
            'ready_for_pickup' => self::Washed,
            'delivering' => self::Delivering,
            'delivered' => self::Delivered,
            'completed', 'paid' => self::Paid,
            'cancelled' => self::Cancelled,
            default => self::tryFrom((string) $value) ?? $default,
        };
    }
}
