<?php

namespace App\Support;

final class BankTransferReference
{
    public static function forOrder(int $orderId): string
    {
        return strtoupper((string) config('services.beepay.payment_prefix', 'DH'))
            .str_pad((string) $orderId, 8, '0', STR_PAD_LEFT);
    }
}
