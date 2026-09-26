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
