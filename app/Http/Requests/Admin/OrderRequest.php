<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('order') ?? $this->route('id') ?? null;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:orders,code'],
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'weight_kg' => ['nullable', 'string', 'max:50'],
            'quantity_items' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,processing,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'service_id.required' => 'Dịch vụ là bắt buộc.',
            'service_id.exists' => 'Dịch vụ không tồn tại.',
            'total_amount.required' => 'Tổng tiền là bắt buộc.',
            'total_amount.numeric' => 'Tổng tiền phải là số.',
            'total_amount.min' => 'Tổng tiền không được nhỏ hơn 0.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Ghi chú không quá 1000 ký tự.',
        ];
    }
}
