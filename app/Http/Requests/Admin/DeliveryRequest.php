<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('delivery') ?? $this->route('id') ?? null;

        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['required', 'exists:orders,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'address' => ['nullable', 'string', 'max:500'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', 'in:pending,picking,delivering,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'order_id.required' => 'Đơn hàng là bắt buộc để tạo giao nhận.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'employee_id.exists' => 'Nhân viên không tồn tại.',
            'method.required' => 'Phương thức giao nhận là bắt buộc.',
            'method.in' => 'Phương thức không hợp lệ. Chỉ chấp nhận: nhan_do, giao_do.',
            'pickup_date.required' => 'Ngày lấy hàng là bắt buộc.',
            'pickup_time.required' => 'Giờ lấy hàng là bắt buộc.',
            'pickup_time.date_format' => 'Định dạng giờ: HH:MM.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'address.max' => 'Không quá 500 ký tự.',
            'notes.max' => 'Không quá 1000 ký tự.',
        ];
    }
}