<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use Illuminate\Validation\Validator;

class XacNhanBookingRequest extends LuuBookingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['status']);
        unset($rules['staff_id']);

        return array_merge($rules, [
            'items' => ['required', 'array', 'min:1'],
            'items.*.TinhTrangTruocKhiGiat' => ['required', 'string', 'max:320'],
            'items.*.GhiChu' => ['nullable', 'string', 'max:160'],
            'DiemSuDung' => ['prohibited'],
            'use_points' => ['prohibited'],
            'customer_accepted' => ['required', 'accepted'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'items.required' => 'Vui lòng nhập ít nhất một dòng kiểm tra thực tế trước khi tạo đơn.',
            'items.min' => 'Vui lòng nhập ít nhất một dòng kiểm tra thực tế trước khi tạo đơn.',
            'items.*.TinhTrangTruocKhiGiat.required' => 'Vui lòng nhập tình trạng trước khi giặt cho từng dòng.',
            'customer_accepted.required' => 'Xác nhận khách hàng đồng ý với kết quả kiểm kê và tổng tiền là bắt buộc.',
            'customer_accepted.accepted' => 'Chỉ được tạo đơn sau khi ghi nhận khách hàng đã đồng ý với kết quả kiểm kê và tổng tiền.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            $routeBooking = $this->route('booking');
            $booking = $routeBooking instanceof Booking ? $routeBooking : Booking::query()->find($routeBooking);
            if ($booking && ! $booking->donHangs()->exists()) {
                if ($booking->statusEnum() !== BookingStatus::Confirmed) {
                    $validator->errors()->add('booking', 'Hãy xác nhận Booking trước khi kiểm kê và tạo đơn.');
                } elseif (! BookingGiaoNhan::query()->where('BookingID', $booking->BookingID)
                    ->where('LoaiGiaoNhan', 'NHAN_DO')
                    ->whereIn('TrangThai', [
                        DeliveryStatus::Pending->dbValue(),
                        DeliveryStatus::Picking->dbValue(),
                    ])
                    ->whereNull('ThoiGianThucTe')
                    ->whereNull('DonHangID')
                    ->exists()) {
                    $validator->errors()->add('booking', 'Phiếu nhận không còn ở trạng thái cho phép kiểm kê và tạo đơn.');
                }
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return route('bookings.inspection', $this->route('booking'));
    }
}
