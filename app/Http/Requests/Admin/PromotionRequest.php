<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('promotion') ?? $this->route('id') ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:promotions,code,' . ($id ?? '')],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', $this->quantityNotBelowUsedCount($id)],
            'conditions' => ['nullable', 'array'],
            'conditions.'.Promotion::CONDITION_FIRST_ORDER_ONLY => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(RecordStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'Số lượng mã phát ra phải lớn hơn 0.',
            'quantity.integer' => 'Số lượng mã phát ra phải là số nguyên.',
            'conditions.'.Promotion::CONDITION_FIRST_ORDER_ONLY.'.boolean' => 'Điều kiện "chỉ đơn hàng đầu tiên" phải là đúng hoặc sai.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    /**
     * Không cho đặt số lượng mã phát ra thấp hơn số lượt đã dùng.
     */
    private function quantityNotBelowUsedCount(mixed $id): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($id): void {
            if ($value === null || $value === '') {
                return;
            }

            $usedCount = Promotion::withTrashed()->find($id)?->used_count ?? 0;

            if ((int) $value < (int) $usedCount) {
                $fail('Số lượng mã phát ra không được nhỏ hơn số lượt đã sử dụng.');
            }
        };
    }
}
