<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryRules
{
    public static function assertMutableOrder(DonHang $order): void
    {
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
            'employee_id' => ['required', 'integer', EmployeeAssignment::rule($current?->NhanVienID)],
            'status' => ['required', 'in:'.implode(',', DeliveryStatus::values())],
            'pickup_date' => ['nullable', 'required_if:method,nhan_do', 'required_if:status,picking,delivering,completed', 'required_with:pickup_time', 'date_format:Y-m-d'],
            'pickup_time' => ['nullable', 'required_if:method,nhan_do', 'required_if:status,picking,delivering,completed', 'required_with:pickup_date', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'employee_id.required' => 'Vui lòng chọn nhân viên phụ trách.',
            'employee_id.integer' => 'Nhân viên phụ trách không hợp lệ.',
            'employee_id.exists' => 'Vui lòng chọn nhân viên đang hoạt động.',
            'pickup_date.required_if' => 'Vui lòng chọn ngày giao nhận trước khi tiếp tục.',
            'pickup_date.required_with' => 'Vui lòng chọn ngày giao nhận.',
            'pickup_date.date_format' => 'Ngày giao nhận không hợp lệ.',
            'pickup_time.required_if' => 'Vui lòng chọn giờ giao nhận trước khi tiếp tục.',
            'pickup_time.required_with' => 'Vui lòng chọn giờ giao nhận.',
            'pickup_time.date_format' => 'Định dạng giờ giao nhận không hợp lệ.',
        ])->validate();

        if (! empty($data['employee_id'])) {
            EmployeeAssignment::assertAssignable((int) $data['employee_id'], 'employee_id', $current?->NhanVienID);
        }
        if ($current && ((int) $data['order_id'] !== $current->DonHangID || strtoupper($data['method']) !== $current->LoaiGiaoNhan)) {
            throw ValidationException::withMessages(['order_id' => 'Không thể đổi đơn hàng hoặc chặng của phiếu đã tạo.']);
        }
        if ($current && $data['fulfillment'] !== $current->HinhThuc) {
            throw ValidationException::withMessages(['fulfillment' => 'Không thể đổi hình thức của phiếu giao nhận đã tạo.']);
        }
        $target = DeliveryStatus::from($data['status']);
        $previous = $current
            ? DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan)
            : DeliveryStatus::Pending;
        $isReturnLeg = $data['method'] === 'giao_do';

        if ($isReturnLeg) {
            if ($data['fulfillment'] !== 'Tại nhà' || ! $order->requiresHomeDelivery()) {
                throw ValidationException::withMessages([
                    'method' => 'Chỉ đơn có phương thức trả đồ tại nhà mới được có chặng giao đồ.',
                ]);
            }
            if ($isReturnLeg && ! $current && $target !== DeliveryStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu giao đồ mới phải được lưu ở trạng thái chờ trước khi bắt đầu giao.',
                ]);
            }
            if (! $current && ! in_array($order->statusEnum(), [OrderStatus::Washed, OrderStatus::Delivering], true)) {
                throw ValidationException::withMessages([
                    'order_id' => 'Chỉ đơn đã hoàn thành giặt mới được lập chặng giao đồ.',
                ]);
            }
        }

        if ($previous !== $target && (
            $previous === DeliveryStatus::Completed
            || $previous === DeliveryStatus::Cancelled
            || (in_array($previous, [DeliveryStatus::Picking, DeliveryStatus::Delivering], true) && $target === DeliveryStatus::Pending)
        )) {
            throw ValidationException::withMessages(['status' => 'Không thể mở lại hoặc lùi trạng thái giao nhận.']);
        }
        if (($data['status'] === 'picking' && $data['method'] !== 'nhan_do') || ($data['status'] === 'delivering' && $data['method'] !== 'giao_do')) {
            throw ValidationException::withMessages(['status' => 'Trạng thái không khớp với chặng nhận/giao đồ.']);
        }
        $executing = in_array($target, [DeliveryStatus::Picking, DeliveryStatus::Delivering, DeliveryStatus::Completed], true);
        if ($executing && (! $current || $target !== $previous)) {
            $allowed = $data['method'] === 'nhan_do'
                ? [OrderStatus::Pending, OrderStatus::Received]
                : [OrderStatus::Washed, OrderStatus::Delivering, OrderStatus::Delivered, OrderStatus::Paid];
            if (! in_array($order->statusEnum(), $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Đơn hàng chưa ở trạng thái cho phép thực hiện chặng giao nhận này.']);
            }
        }
        if ($isReturnLeg && $target === DeliveryStatus::Delivering && $previous !== DeliveryStatus::Delivering) {
            if (empty($data['employee_id'])) {
                throw ValidationException::withMessages(['employee_id' => 'Hãy phân công nhân viên đang hoạt động trước khi bắt đầu giao.']);
            }
            EmployeeAssignment::assertAssignable((int) $data['employee_id'], 'employee_id');
        }
        if ($isReturnLeg && $target === DeliveryStatus::Completed && $previous !== DeliveryStatus::Delivering) {
            throw ValidationException::withMessages(['status' => 'Chỉ có thể hoàn thành chặng giao đang được thực hiện.']);
        }
        if ($isReturnLeg && $target === DeliveryStatus::Completed && $order->statusEnum() !== OrderStatus::Delivering) {
            throw ValidationException::withMessages(['status' => 'Đơn hàng phải ở trạng thái đang giao trước khi hoàn tất chặng.']);
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
