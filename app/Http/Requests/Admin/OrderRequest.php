<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
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
            'code' => ['nullable', 'string', 'max:50', 'unique:orders,code,' . ($id ?? '')],
            'customer_id' => ['required', 'exists:customers,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'promotion_id' => ['nullable', 'exists:promotions,id'],
            'promotion_code' => ['nullable', 'string', 'max:100'],
            'points_used' => ['nullable', 'integer', 'min:0'],
            'weight_kg' => ['nullable', 'string', 'max:50'],
            'quantity_items' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', OrderStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.garment_id' => ['nullable', 'exists:garments,id'],
            'items.*.item_name' => ['nullable', 'string', 'max:255'],
            'items.*.item_type' => ['nullable', 'in:garment,service'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'employee_id.exists' => 'Nhân viên không tồn tại.',
            'promotion_id.exists' => 'Chương trình khuyến mãi không tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Ghi chú không quá 1000 ký tự.',
            'items.*.quantity.integer' => 'Số lượng phải là số nguyên.',
            'items.*.quantity.min' => 'Số lượng không được nhỏ hơn 0.',
        ];
    }
}