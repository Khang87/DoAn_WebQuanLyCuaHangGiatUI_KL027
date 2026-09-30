<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingMethod;
use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:KhachHang,KhachHangID'],
            'staff_id' => ['nullable', 'exists:NhanVien,NhanVienID'],
            'method' => ['required', 'in:'.implode(',', BookingMethod::values())],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:'.implode(',', BookingStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'staff_id.exists' => 'Nhân viên không tồn tại.',
            'method.required' => 'Phương thức nhận/giao đồ là bắt buộc.',
            'method.in' => 'Phương thức không hợp lệ. Chỉ chấp nhận: '.implode(', ', BookingMethod::values()).'.',
            'scheduled_date.required' => 'Ngày dự kiến là bắt buộc.',
            'scheduled_time.required' => 'Giờ dự kiến là bắt buộc.',
            'scheduled_time.date_format' => 'Định dạng giờ: HH:MM.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Không quá 1000 ký tự.',
        ];
    }
}
