<?php

namespace App\Support;

use App\Enums\DeliveryStatus;

/**
 * Hành động tiến trình của một phiếu giao nhận.
 *
 * Danh sách là nơi đặt nút này; trang chi tiết chỉ hiển thị thông tin.
 */
class DeliveryWorkflow
{
    /**
     * @return array{label: string, icon: string, color: string}|null
     */
    public static function progressAction(string $deliveryType, ?string $fulfillment, DeliveryStatus $status): ?array
    {
        $isPickup = mb_strtoupper($deliveryType) === 'NHAN_DO';
        $isInStore = $fulfillment === 'Tại cửa hàng';

        return match ($status) {
            DeliveryStatus::Pending => $isPickup
                ? self::action('Bắt đầu nhận đồ', 'bi-box-arrow-in-down', '#2563eb')
                : self::action(
                    $isInStore ? 'Xác nhận đã bàn giao' : 'Bắt đầu giao đồ',
                    $isInStore ? 'bi-check-circle' : 'bi-truck',
                    '#2563eb',
                ),
            DeliveryStatus::Picking => self::action('Xác nhận đã nhận đồ', 'bi-check-circle', '#16a34a'),
            DeliveryStatus::Delivering => self::action('Xác nhận đã giao đồ', 'bi-check-circle', '#16a34a'),
            default => null,
        };
    }

    /**
     * @return array{label: string, icon: string, color: string}
     */
    private static function action(string $label, string $icon, string $color): array
    {
        return compact('label', 'icon', 'color');
    }
}
