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
            'service_id' => ['required', 'exists:services,id'],
            'garment_type' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'delivery_method' => ['required', 'in:pickup,dropoff'],
            'address' => ['nullable', 'string', 'max:500'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'service_id.required' => 'Dịch vụ là bắt buộc.',
            'garment_type.required' => 'Loại đồ giặt là bắt buộc.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng không được nhỏ hơn 1.',
            'delivery_method.required' => 'Phương thức giao nhận là bắt buộc.',
            'pickup_date.required' => 'Ngày lấy hàng là bắt buộc.',
            'pickup_time.required' => 'Giờ lấy hàng là bắt buộc.',
            'pickup_time.date_format' => 'Định dạng giờ: HH:MM.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'notes.max' => 'Không quá 1000 ký tự.',
        ];
    }
}
