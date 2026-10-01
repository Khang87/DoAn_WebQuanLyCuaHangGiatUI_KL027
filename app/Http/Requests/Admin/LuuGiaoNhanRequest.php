<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuGiaoNhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:DonHang,DonHangID'],
            'employee_id' => ['nullable', 'integer', 'exists:NhanVien,NhanVienID'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'address' => ['required', 'string', 'max:255'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', 'in:'.implode(',', DeliveryStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Đơn hàng là bắt buộc để tạo giao nhận.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'employee_id.exists' => 'Nhân viên không tồn tại.',
            'method.required' => 'Loại giao nhận là bắt buộc.',
            'method.in' => 'Loại giao nhận không hợp lệ.',
            'address.required' => 'Địa chỉ giao nhận là bắt buộc.',
            'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'pickup_date.required' => 'Ngày giao nhận là bắt buộc.',
            'pickup_time.required' => 'Giờ giao nhận là bắt buộc.',
            'pickup_time.date_format' => 'Định dạng giờ phải là HH:MM.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }
}
