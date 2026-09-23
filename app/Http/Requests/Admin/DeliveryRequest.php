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
            'method' => ['required', 'in:pickup,dropoff'],
            'address' => ['nullable', 'string', 'max:500'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'method.required' => 'Phương thức giao nhận là bắt buộc.',
            'pickup_date.required' => 'Ngày lấy hàng là bắt buộc.',
            'pickup_time.required' => 'Giờ lấy hàng là bắt buộc.',
            'pickup_time.date_format' => 'Định dạng giờ: HH:MM.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'address.max' => 'Không quá 500 ký tự.',
        ];
    }
}
