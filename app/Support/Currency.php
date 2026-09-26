<?php

namespace App\Support;

/**
 * Định dạng tiền tệ duy nhất của hệ thống (Việt Nam đồng).
 */
class Currency
{
    public const CODE = 'VNĐ';

    /**
     * Ví dụ: 1250000 => "1.250.000 VNĐ".
     */
    public static function format(float|int|string|null $amount, bool $withCode = true): string
    {
        $formatted = number_format((float) $amount, 0, ',', '.');

        return $withCode ? $formatted.' '.self::CODE : $formatted;
    }
}
