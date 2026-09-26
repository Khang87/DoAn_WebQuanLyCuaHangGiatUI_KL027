<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
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
            'status' => ['required', 'in:'.implode(',', RecordStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'MÃ£ coupon lÃ  báº¯t buá»™c.',
            'code.unique' => 'MÃ£ coupon Ä‘Ã£ tá»“n táº¡i.',
            'promotion_id.required' => 'ChÆ°Æ¡ng trÃ¬nh khuyáº¿n mÃ£i lÃ  báº¯t buá»™c.',
            'promotion_id.exists' => 'ChÆ°Æ¡ng trÃ¬nh khÃ´ng tá»“n táº¡i.',
            'discount_type.required' => 'Loáº¡i giáº£m giÃ¡ lÃ  báº¯t buá»™c.',
            'discount_value.required' => 'GiÃ¡ trá»‹ giáº£m giÃ¡ lÃ  báº¯t buá»™c.',
            'discount_value.numeric' => 'GiÃ¡ trá»‹ pháº£i lÃ  sá»‘.',
            'max_uses.integer' => 'Sá»‘ láº§n sá»­ dá»¥ng pháº£i lÃ  sá»‘ nguyÃªn.',
            'expires_at.required' => 'NgÃ y háº¿t háº¡n lÃ  báº¯t buá»™c.',
            'status.required' => 'Tráº¡ng thÃ¡i lÃ  báº¯t buá»™c.',
        ];
    }
}
