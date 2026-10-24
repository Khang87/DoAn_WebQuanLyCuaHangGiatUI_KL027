<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\DonHang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Đếm đơn hàng theo trạng thái vận hành.
 *
 * "Đã thanh toán" là trạng thái cũ chỉ việc đã thu tiền, không phải một mốc vận
 * hành, nên được quy về trạng thái trước khi thanh toán (mặc định "Đã giao") dựa
 * trên nhật ký chuyển trạng thái. Nhờ vậy Tổng đơn hàng luôn bằng đúng tổng ba
 * nhóm Hoàn thành / Đang xử lý / Đã hủy và không còn trạng thái mồ côi.
 */
class OrderStatusRollup
{
    /**
     * @param  Builder<DonHang>  $orders
     * @return array{distribution: Collection<string, int>, completed: int, processing: int, cancelled: int, total: int}
     */
    public static function counts(Builder $orders): array
    {
        $distribution = (clone $orders)
            ->selectRaw('"TrangThai", COUNT(*) AS count')
            ->groupBy('TrangThai')
            ->pluck('count', 'TrangThai')
            ->map(fn ($count): int => (int) $count);

        if ($distribution->has(OrderStatus::Paid->value)) {
            $legacyPaidOrders = (clone $orders)
                ->where('TrangThai', OrderStatus::Paid->value)
                ->with('statusChanges')
                ->get(['DonHangID', 'TrangThai']);

            $distribution->forget(OrderStatus::Paid->value);
            foreach ($legacyPaidOrders as $order) {
                $status = $order->operationalStatusEnum()->value;
                $distribution->put($status, $distribution->get($status, 0) + 1);
            }
        }

        return [
            'distribution' => $distribution,
            'completed' => $distribution->get(OrderStatus::Delivered->value, 0),
            'cancelled' => $distribution->get(OrderStatus::Cancelled->value, 0),
            'processing' => $distribution->except([
                OrderStatus::Delivered->value,
                OrderStatus::Cancelled->value,
            ])->sum(),
            'total' => $distribution->sum(),
        ];
    }
}
