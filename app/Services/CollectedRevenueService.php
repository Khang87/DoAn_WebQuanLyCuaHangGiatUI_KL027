<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\ThanhToan;
use Illuminate\Database\Eloquent\Builder;

class CollectedRevenueService
{
    /**
     * Include refunded-status rows as gross receipts: there is no refund ledger
     * with refund amount/time from which net revenue could be calculated safely.
     *
     * @return Builder<ThanhToan>
     */
    public function query(): Builder
    {
        return ThanhToan::query()
            ->whereIn('TrangThai', [
                PaymentStatus::Paid->value,
                PaymentStatus::Refunded->value,
            ]);
    }
}
