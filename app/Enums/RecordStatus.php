<?php

namespace App\Enums;

/**
 * Nguồn dữ liệu chuẩn cho trạng thái bản ghi dùng chung của các module
 * (dịch vụ, loại đồ giặt, bảng giá, khuyến mãi, nhóm dịch vụ, tài khoản...).
 *
 * Giá trị lưu trong database là "active" / "inactive" — đúng với giá trị mặc
 * định đã có sẵn trong các migration nên không phải đổi dữ liệu cũ.
 */
enum RecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Nhãn tiếng Việt hiển thị duy nhất cho trạng thái này.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hoạt động',
            self::Inactive => 'Tạm ngưng',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'bg-success-subtle text-success border-success',
            self::Inactive => 'bg-secondary-subtle text-secondary border-secondary',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Danh sách value => label dùng cho thẻ <option> của các form quản trị.
     *
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
     * Chuyển giá trị bất kỳ (kể cả giá trị cũ) về enum, không ném lỗi.
     */
    public static function parse(mixed $value, self $default = self::Inactive): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return self::tryFrom($normalized)
            ?? self::legacyFrom($normalized)
            ?? $default;
    }

    /**
     * Giá trị trạng thái tồn tại từ các phiên bản cũ của dự án.
     */
    private static function legacyFrom(string $value): ?self
    {
        return match ($value) {
            '1', 'on', 'true', 'yes', 'published', 'visible' => self::Active,
            '0', 'off', 'false', 'no', 'draft', 'hidden' => self::Inactive,
            default => null,
        };
    }
}
