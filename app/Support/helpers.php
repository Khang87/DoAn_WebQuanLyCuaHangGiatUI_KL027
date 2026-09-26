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
