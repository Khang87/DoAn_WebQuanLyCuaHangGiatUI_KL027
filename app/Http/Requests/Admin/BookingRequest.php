<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('booking') ?? $this->route('id') ?? null;

        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'staff_id' => ['nullable', 'exists:users,id'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:pending,confirmed,arrived,completed,cancelled'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'staff_id.exists' => 'Nhân viên không tồn tại.',
            'method.required' => 'Phương thức nhận/giao đồ là bắt buộc.',
            'method.in' => 'Phương thức không hợp lệ. Chỉ chấp nhận: nhan_do, giao_do.',
            'scheduled_date.required' => 'Ngày dự kiến là bắt buộc.',
            'scheduled_time.required' => 'Giờ dự kiến là bắt buộc.',
            'scheduled_time.date_format' => 'Định dạng giờ: HH:MM.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Không quá 1000 ký tự.',
        ];
    }
}