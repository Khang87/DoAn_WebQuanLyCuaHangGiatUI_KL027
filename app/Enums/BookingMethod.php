<?php

namespace App\Enums;

/**
 * Hình thức nhận/giao đồ của lịch hẹn.
 *
 * Giá trị lưu trong database (bảng `Booking`, cột `HinhThucNhanDo`) là tiếng
 * Việt có dấu, khớp với ràng buộc CHECK `Booking_HinhThucNhanDo_check`:
 *
 *   'Tại cửa hàng' -> khách tự mang đến cửa hàng (Nhận đồ)
 *   'Tại nhà'      -> nhân viên đến tận nhà khách (Giao đồ)
 *
 * Nguồn duy nhất cho module Đặt lịch: select trong form chỉnh sửa, select lọc
 * ngoài bảng và nhãn hiển thị.
 */
enum BookingMethod: string
{
    case NhanDo = 'Tại cửa hàng';
    case GiaoDo = 'Tại nhà';

    public function label(): string
    {
        return match ($this) {
            self::NhanDo => 'Nhận đồ',
            self::GiaoDo => 'Giao đồ',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NhanDo => 'bi-box-arrow-in-down',
            self::GiaoDo => 'bi-truck',
        };
    }

    /**
     * Mã `LoaiGiaoNhan` tương ứng trong bảng `GiaoNhan` (ràng buộc
     * `GiaoNhan_LoaiGiaoNhan_check`).
     */
    public function deliveryType(): string
    {
        return match ($this) {
            self::NhanDo => 'NHAN_DO',
            self::GiaoDo => 'GIAO_DO',
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

    public static function parse(mixed $value, self $default = self::NhanDo): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $raw = trim((string) $value);

        return match (mb_strtolower($raw)) {
            'nhan_do', 'nhận đồ', 'tại cửa hàng' => self::NhanDo,
            'giao_do', 'giao đồ', 'tại nhà' => self::GiaoDo,
            default => self::tryFrom($raw) ?? $default,
        };
    }

    public static function labelFor(mixed $value, self $default = self::NhanDo): string
    {
        return self::parse($value, $default)->label();
    }

    public static function iconFor(mixed $value, self $default = self::NhanDo): string
    {
        return self::parse($value, $default)->icon();
    }
}
