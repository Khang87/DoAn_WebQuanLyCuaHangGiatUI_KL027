<?php

namespace App\Enums;

/**
 * Trạng thái hiển thị của đánh giá khách hàng.
 *
 * Nguồn duy nhất cho module Đánh giá: dropdown lọc ngoài bảng, form chỉnh sửa
 * và badge trong cột Trạng thái. Tập giá trị khớp với ReviewRequest
 * ('in:visible,hidden').
 */
enum ReviewStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Visible => 'Hiển thị',
            self::Hidden => 'Ẩn',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Visible => 'bg-success-subtle text-success-emphasis border border-success',
            self::Hidden => 'bg-secondary-subtle text-secondary-emphasis border border-secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Visible => 'eye',
            self::Hidden => 'eye-slash',
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

    public static function parse(mixed $value, self $default = self::Visible): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return self::tryFrom(mb_strtolower(trim((string) $value))) ?? $default;
    }

    public static function labelFor(mixed $value, self $default = self::Visible): string
    {
        return self::parse($value, $default)->label();
    }

    public static function badgeClassFor(mixed $value, self $default = self::Visible): string
    {
        return self::parse($value, $default)->badgeClass();
    }

    public static function iconFor(mixed $value, self $default = self::Visible): string
    {
        return self::parse($value, $default)->icon();
    }
}
