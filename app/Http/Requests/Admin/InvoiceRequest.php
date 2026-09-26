<?php

namespace App\Http\Requests\Admin;

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
            'invoice_date' => ['nullable', 'date'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:unpaid,partial,paid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Đơn hàng là bắt buộc.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'total.numeric' => 'Tổng tiền phải là số.',
            'total.min' => 'Tổng tiền không được nhỏ hơn 0.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Không quá 1000 ký tự.',
        ];
    }
}