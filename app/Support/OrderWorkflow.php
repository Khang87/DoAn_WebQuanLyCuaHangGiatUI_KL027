<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\GiaoNhan;

/**
 * Hành động vận hành của một đơn hàng, dùng chung cho cột "Thao tác" ở danh
 * sách và trang chi tiết.
 *
 * Danh sách là nơi duy nhất chứa các nút này; trang chi tiết chỉ hiển thị
 * thông tin và form của đúng một hành động được bấm từ ngoài.
 */
class OrderWorkflow
{
    public const INTENT_RECEIVING = 'tiep-nhan';

    public const LIST_RETURN_PARAM = 'from';

    public const LIST_RETURN_VALUE = 'don-hang';

    /**
     * Tham số gắn vào liên kết tới màn hình khác mở ra từ cột "Thao tác" của
     * danh sách đơn hàng, để nút "Quay lại" ở đích đưa người dùng về lại danh
     * sách thay vì về danh sách của module khác.
     *
     * @return array<string, string>
     */
    public static function listReturnParams(): array
    {
        return [self::LIST_RETURN_PARAM => self::LIST_RETURN_VALUE];
    }

    /**
     * @param  string  $fallback  đích "Quay lại" khi màn hình mở từ nơi khác
     */
    public static function listBackUrl(string $fallback): string
    {
        return request()->query(self::LIST_RETURN_PARAM) === self::LIST_RETURN_VALUE
            ? route('orders.index')
            : $fallback;
    }

    /**
     * Bước kế tiếp trong chuỗi xử lý đơn, hoặc null khi đơn đã dừng.
     *
     * @return array{kind: string, status: ?OrderStatus, label: string, icon: string, variant: string}|null
     */
    public static function nextAction(DonHang $order): ?array
    {
        $status = $order->operationalStatusEnum();

        if ($order->booking !== null && in_array($status, [OrderStatus::Washed, OrderStatus::Delivering], true)) {
            return null;
        }

        $next = $status->nextProcessStatus();

        if ($status === OrderStatus::Washed && ! $order->requiresHomeDelivery()) {
            $next = $order->booking === null ? OrderStatus::Delivered : null;
        }

        return match ($next) {
            OrderStatus::Washing => self::action('status', $next, 'Bắt đầu giặt', 'bi-play-circle', 'primary'),
            OrderStatus::Washed => self::action('status', $next, 'Hoàn thành giặt', 'bi-check2-circle', 'success'),
            OrderStatus::Delivering => self::action('delivery', $next, 'Bắt đầu giao hàng', 'bi-truck', 'primary'),
            OrderStatus::Delivered => self::action(
                'status',
                $next,
                $order->requiresHomeDelivery() ? 'Xác nhận đã giao' : 'Xác nhận khách đã nhận tại cửa hàng',
                'bi-box-seam',
                'success',
            ),
            default => null,
        };
    }

    /**
     * @return array{kind: string, status: OrderStatus, label: string, icon: string, variant: string}
     */
    private static function action(string $kind, OrderStatus $status, string $label, string $icon, string $variant): array
    {
        return compact('kind', 'status', 'label', 'icon', 'variant');
    }

    /**
     * Đơn chờ kiểm tra thực tế: hành động tiếp nhận nằm ở trang chi tiết vì
     * phải nhập số lượng và tình trạng từng mặt hàng.
     */
    public static function awaitsReceiving(DonHang $order): bool
    {
        return $order->operationalStatusEnum() === OrderStatus::Pending;
    }

    public static function receivingUrl(DonHang $order): string
    {
        return route('orders.show', [$order, 'action' => self::INTENT_RECEIVING]);
    }

    /**
     * Phiếu giao hàng (GIAO_DO) còn hiệu lực gần nhất của đơn.
     */
    public static function activeReturnDelivery(DonHang $order): ?GiaoNhan
    {
        return $order->giaoNhans
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->sortByDesc('GiaoNhanID')
            ->first(fn (GiaoNhan $delivery): bool => DeliveryStatus::parseForLeg(
                $delivery->TrangThai,
                $delivery->LoaiGiaoNhan,
            ) !== DeliveryStatus::Cancelled);
    }
}
