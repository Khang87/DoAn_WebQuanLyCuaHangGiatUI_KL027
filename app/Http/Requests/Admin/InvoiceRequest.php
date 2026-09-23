<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('invoice') ?? $this->route('id') ?? null;

        return [
            'order_id' => ['required', 'exists:orders,id'],
            'total' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:unpaid,partial,paid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Đơn hàng là bắt buộc.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'total.required' => 'Tổng tiền là bắt buộc.',
            'total.numeric' => 'Tổng tiền phải là số.',
            'total.min' => 'Không được nhỏ hơn 0.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'notes.max' => 'Không quá 1000 ký tự.',
        ];
    }
}
