<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'service_id' => ['required', 'exists:services,id'],
            'garment_category_id' => ['nullable', 'exists:garment_categories,id'],
            'garment_id' => ['required', 'exists:garments,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'item_type' => ['required', 'in:garment,service'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'weight' => ['nullable', 'regex:/^\d+(\.\d+)?$/', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Dịch vụ là bắt buộc.',
            'service_id.exists' => 'Dịch vụ không tồn tại.',
            'garment_id.required' => 'Loại đồ giặt là bắt buộc.',
            'garment_id.exists' => 'Loại đồ giặt không tồn tại.',
            'item_name.required' => 'Tên mặt hàng là bắt buộc.',
            'item_name.max' => 'Tên mặt hàng không quá 255 ký tự.',
            'item_type.required' => 'Loại mặt hàng là bắt buộc.',
            'item_type.in' => 'Loại mặt hàng không hợp lệ.',
            'price.required' => 'Đơn giá là bắt buộc.',
            'price.numeric' => 'Đơn giá phải là số.',
            'price.min' => 'Đơn giá không được nhỏ hơn 0.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn hoặc bằng 1.',
            'weight.regex' => 'Khối lượng chỉ được nhập số dương và số thập phân (VD: 0.5, 1.2).',
            'weight.gt' => 'Khối lượng phải lớn hơn 0.',
            'notes.max' => 'Ghi chú không quá 1000 ký tự.',
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước khi validate: đảm bảo weight là số nếu rỗng.
     */
    protected function prepareForValidation(): void
    {
        $weight = $this->input('weight');
        if ($weight === '' || $weight === null) {
            $this->merge(['weight' => 0]);
        }
    }
}