<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryRules
{
    public static function assertMutableOrder(DonHang $order): void
    {
        if ($order->isLocked()) {
            throw SettledOrderException::forOrder($order->MaDonHang);
        }
        if ($order->statusEnum() === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['order_id' => 'Không thể thay đổi giao nhận của đơn đã hủy.']);
        }
    }

    public static function validate(array $data, DonHang $order, ?GiaoNhan $current = null): array
    {
        $data = array_merge($current ? self::inputFor($current) : ['status' => 'pending', 'fulfillment' => 'Tại nhà'], $data);
        $data['status'] = $data['status'] ?: 'pending';
        Validator::make($data, [
            'order_id' => ['required', 'integer'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'fulfillment' => ['required', 'in:Tại cửa hàng,Tại nhà'],
            'address' => ['nullable', 'required_if:fulfillment,Tại nhà', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', EmployeeAssignment::rule($current?->NhanVienID)],
            'status' => ['required', 'in:'.implode(',', DeliveryStatus::values())],
            'pickup_date' => ['nullable', 'required_if:method,nhan_do', 'required_if:status,picking,delivering,completed', 'required_with:pickup_time', 'date_format:Y-m-d'],
            'pickup_time' => ['nullable', 'required_if:method,nhan_do', 'required_if:status,picking,delivering,completed', 'required_with:pickup_date', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ])->validate();

        if (! empty($data['employee_id'])) {
            EmployeeAssignment::assertAssignable((int) $data['employee_id'], 'employee_id', $current?->NhanVienID);
        }
        if ($current && ((int) $data['order_id'] !== $current->DonHangID || strtoupper($data['method']) !== $current->LoaiGiaoNhan)) {
            throw ValidationException::withMessages(['order_id' => 'Không thể đổi đơn hàng hoặc chặng của phiếu đã tạo.']);
        }
        $target = DeliveryStatus::parse($data['status']);
        $previous = $current ? DeliveryStatus::parse($current->TrangThai) : DeliveryStatus::Pending;
        if ($previous !== $target && ($previous === DeliveryStatus::Completed || $previous === DeliveryStatus::Cancelled || ($previous === DeliveryStatus::Delivering && $target === DeliveryStatus::Pending))) {
            throw ValidationException::withMessages(['status' => 'Không thể mở lại hoặc lùi trạng thái giao nhận.']);
        }
        if (($data['status'] === 'picking' && $data['method'] !== 'nhan_do') || ($data['status'] === 'delivering' && $data['method'] !== 'giao_do')) {
            throw ValidationException::withMessages(['status' => 'Trạng thái không khớp với chặng nhận/giao đồ.']);
        }
        $executing = in_array($target, [DeliveryStatus::Delivering, DeliveryStatus::Completed], true);
        if ($executing && (! $current || $target !== $previous)) {
            $allowed = $data['method'] === 'nhan_do'
                ? [OrderStatus::Pending, OrderStatus::Received]
                : [OrderStatus::Washed, OrderStatus::Delivering, OrderStatus::Delivered];
            if (! in_array($order->statusEnum(), $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Đơn hàng chưa ở trạng thái cho phép thực hiện chặng giao nhận này.']);
            }
        }
        self::validateSchedule($data, $current);

        return $data;
    }

    public static function validateSchedule(array $data, ?GiaoNhan $current = null): void
    {
        if (empty($data['pickup_date']) || empty($data['pickup_time'])) {
            return;
        }
        $scheduled = Carbon::createFromFormat('!Y-m-d H:i', $data['pickup_date'].' '.$data['pickup_time'], config('app.timezone'));
        $unchanged = $current?->ThoiGianDuKien?->format('Y-m-d H:i') === $scheduled->format('Y-m-d H:i');
        if (! $unchanged && $scheduled->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['pickup_time' => 'Thời gian giao nhận phải sau thời điểm hiện tại.']);
        }
    }

    private static function inputFor(GiaoNhan $delivery): array
    {
        return [
            'order_id' => $delivery->DonHangID,
            'employee_id' => $delivery->NhanVienID,
            'method' => strtolower($delivery->LoaiGiaoNhan),
            'fulfillment' => $delivery->HinhThuc,
            'address' => $delivery->DiaChi,
            'status' => DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)->value,
            'pickup_date' => $delivery->ThoiGianDuKien?->format('Y-m-d'),
            'pickup_time' => $delivery->ThoiGianDuKien?->format('H:i'),
            'notes' => $delivery->GhiChu,
        ];
    }
}
