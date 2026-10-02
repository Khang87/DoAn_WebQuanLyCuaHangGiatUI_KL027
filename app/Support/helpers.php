<?php

use App\Support\Currency;

if (! function_exists('format_currency')) {
    /**
     * Định dạng số tiền theo chuẩn Việt Nam đồng.
     */
    function format_currency(float|int|string|null $amount, bool $withCode = true): string
    {
        return Currency::format($amount, $withCode);
    }
}

if (! function_exists('format_number')) {
    /**
     * Định dạng số nguyên không dấu phân thập phân thừa.
     * Dùng cho các field tiền/đơn vị tính đang bị dư "0" ở cuối do cast decimal:2.
     * Ví dụ: 120000.00 => "120.000", 4500.5 => "4.501" (làm tròn số nguyên).
     */
    function format_number(float|int|string|null $value, bool $withCode = false): string
    {
        return Currency::format($value, $withCode);
    }
}

if (! function_exists('format_weight')) {
    /**
     * Định dạng khối lượng (kg): tối đa 2 chữ số thập phân nhưng KHÔNG giữ
     * các số 0 thừa ở cuối và dùng dấu phẩy thập phân kiểu Việt Nam.
     * Ví dụ: 120.00 => "120", 5.50 => "5,5", 3.25 => "3,25".
     */
    function format_weight(float|int|string|null $weight): string
    {
        if ($weight === null || $weight === '') {
            return '';
        }

        $formatted = rtrim(rtrim(number_format((float) $weight, 2, ',', '.'), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }
}

if (! function_exists('format_quantity')) {
    /**
     * Định dạng số món dạng số nguyên, không kèm phần thập phân.
     */
    function format_quantity(float|int|string|null $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            return '';
        }

        return number_format((float) $quantity, 0, '.', ',');
    }
}

if (! function_exists('format_weight_display')) {
    /**
     * Hiển thị khối lượng với đúng hai chữ số thập phân.
     */
    function format_weight_display(float|int|string|null $weight): string
    {
        if ($weight === null || $weight === '') {
            return '';
        }

        return number_format((float) $weight, 2, '.', ',');
    }
}

if (! function_exists('format_quantity_weight')) {
    /**
     * Định dạng số món và khối lượng cho cùng một dòng chi tiết.
     */
    function format_quantity_weight(float|int|string|null $quantity, float|int|string|null $weight): string
    {
        $parts = [];

        if ($quantity !== null && $quantity !== '') {
            $parts[] = format_quantity($quantity).' món';
        }

        if ($weight !== null && $weight !== '' && (float) $weight > 0) {
            $parts[] = format_weight_display($weight).' kg';
        }

        return implode(' · ', $parts);
    }
}
