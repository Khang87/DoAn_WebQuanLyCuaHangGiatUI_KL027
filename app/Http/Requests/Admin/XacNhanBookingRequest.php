<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\DonHang;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class XacNhanBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $routeBooking = $this->route('booking');
            $bookingId = $routeBooking instanceof Booking
                ? $routeBooking->getKey()
                : $routeBooking;
            $booking = is_numeric($bookingId)
                ? Booking::query()->find((int) $bookingId)
                : null;

            if ($booking !== null && $booking->statusEnum() !== BookingStatus::Pending) {
                $existingOrder = DonHang::query()
                    ->where('BookingID', $booking->BookingID)
                    ->first();
                $message = $existingOrder !== null
                    ? 'Booking này đã có đơn hàng '.$existingOrder->MaDonHang.'; hệ thống không tạo đơn hàng trùng.'
                    : 'Chỉ đặt lịch đang ở trạng thái Chờ xác nhận mới có thể được duyệt.';

                $validator->errors()->add(
                    'booking',
                    $message,
                );
            }
        });
    }
}
