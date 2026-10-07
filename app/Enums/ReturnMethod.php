<?php

namespace App\Enums;

/** Địa điểm thực hiện một chiều giao nhận, độc lập với chiều còn lại. */
enum ReturnMethod: string
{
    case Store = 'Tại cửa hàng';
    case Home = 'Tại nhà';

    public function label(): string
    {
        return $this->value;
    }

    public function icon(): string
    {
        return $this === self::Home ? 'bi-truck' : 'bi-shop';
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_combine(self::values(), self::values());
    }
}
