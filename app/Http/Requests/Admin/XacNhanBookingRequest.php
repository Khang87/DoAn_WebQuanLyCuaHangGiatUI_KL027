<?php

namespace App\Http\Requests\Admin;

use App\Models\Booking;
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
            'recalculate_delivery_fee' => ['sometimes', 'boolean'],
            'quality_difference' => ['sometimes', 'boolean'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'items.required' => 'Vui lòng nhập ít nhất một dòng kiểm tra thực tế trước khi tạo đơn.',
            'items.min' => 'Vui lòng nhập ít nhất một dòng kiểm tra thực tế trước khi tạo đơn.',
            'items.*.TinhTrangTruocKhiGiat.required' => 'Vui lòng nhập tình trạng trước khi giặt cho từng dòng.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            $routeBooking = $this->route('booking');
            $booking = $routeBooking instanceof Booking ? $routeBooking : Booking::query()->find($routeBooking);
            $convertible = config('giatui.booking_two_step')
                ? $booking?->statusEnum() === \App\Enums\BookingStatus::Confirmed
                : $booking?->isConvertibleToOrder();
            if ($booking && ! $convertible && ! $booking->donHangs()->exists()) {
                $validator->errors()->add('booking', 'Chỉ Booking chờ xác nhận mới có thể kiểm tra thực tế và tạo đơn.');
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return route('bookings.inspection', $this->route('booking'));
    }
}
