<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('coupon') ?? $this->route('id') ?? null;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,' . ($id ?? '')],
            'promotion_id' => ['required', 'exists:promotions,id'],
            'discount_type' => ['required', 'in:percent,fixed,free_shipping'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã coupon là bắt buộc.',
            'code.unique' => 'Mã coupon đã tồn tại.',
            'promotion_id.required' => 'Chương trình khuyến mãi là bắt buộc.',
            'promotion_id.exists' => 'Chương trình không tồn tại.',
            'discount_type.required' => 'Loại giảm giá là bắt buộc.',
            'discount_value.required' => 'Giá trị giảm giá là bắt buộc.',
            'discount_value.numeric' => 'Giá trị phải là số.',
            'max_uses.integer' => 'Số lần sử dụng phải là số nguyên.',
            'expires_at.required' => 'Ngày hết hạn là bắt buộc.',
            'status.required' => 'Trạng thái là bắt buộc.',
        ];
    }
}
