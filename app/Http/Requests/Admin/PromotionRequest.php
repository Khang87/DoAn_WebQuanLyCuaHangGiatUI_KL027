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
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'code' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                'unique:promotions,code,' . ($id ?? ''),
            ],
            'discount_type' => ['required', Rule::in(array_keys(Promotion::discountTypeOptions()))],
            'discount_value' => ['required', 'numeric', 'min:0', $this->discountValueWithinType()],
            'min_order_amount' => ['nullable', 'numeric', 'min:1'],
            'max_discount' => ['nullable', 'numeric', 'min:0', $this->maxDiscountOnlyForPercentage()],
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
            'name.min' => 'Tên chương trình phải có ít nhất 3 ký tự.',
            'code.min' => 'Mã khuyến mãi phải có ít nhất 3 ký tự.',
            'code.regex' => 'Mã khuyến mãi chỉ gồm chữ, số, gạch ngang và gạch dưới.',
            'code.unique' => 'Mã khuyến mãi đã tồn tại.',
            'discount_type.required' => 'Loại giảm là bắt buộc.',
            'discount_type.in' => 'Loại giảm không hợp lệ.',
            'discount_value.required' => 'Giá trị giảm là bắt buộc.',
            'discount_value.numeric' => 'Giá trị giảm phải là số.',
            'discount_value.min' => 'Giá trị giảm phải lớn hơn 0.',
            'min_order_amount.numeric' => 'Đơn hàng tối thiểu phải là số.',
            'min_order_amount.min' => 'Đơn hàng tối thiểu phải từ 1 VNĐ trở lên.',
            'max_discount.numeric' => 'Mức giảm tối đa phải là số.',
            'max_discount.min' => 'Mức giảm tối đa phải lớn hơn 0.',
            'usage_limit.integer' => 'Số lượt sử dụng phải là số nguyên.',
            'usage_limit.min' => 'Số lượt sử dụng phải lớn hơn 0.',
            'quantity.integer' => 'Số lượng mã phát ra phải là số nguyên.',
            'quantity.min' => 'Số lượng mã phát ra phải lớn hơn 0.',
            'conditions.'.Promotion::CONDITION_FIRST_ORDER_ONLY.'.boolean' => 'Điều kiện "chỉ đơn hàng đầu tiên" phải là đúng hoặc sai.',
            'starts_at.date' => 'Ngày bắt đầu không hợp lệ.',
            'expires_at.date' => 'Ngày hết hạn không hợp lệ.',
            'expires_at.after_or_equal' => 'Ngày hết hạn phải từ ngày bắt đầu trở đi.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    /**
     * Ràng buộc theo loại giảm: phần trăm không vượt 100%, số tiền cố định tối thiểu 1.000đ.
     */
    private function discountValueWithinType(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $type = $this->input('discount_type');

            if ($type === Promotion::DISCOUNT_PERCENTAGE && (float) $value > 100) {
                $fail('Giá trị giảm theo phần trăm không được vượt quá 100%.');
            }

            if ($type === Promotion::DISCOUNT_FIXED && (float) $value < 1000) {
                $fail('Giá trị giảm cố định tối thiểu là 1.000 VNĐ.');
            }
        };
    }

    /**
     * Mức giảm tối đa chỉ có ý nghĩa khi giảm theo phần trăm.
     */
    private function maxDiscountOnlyForPercentage(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($this->input('discount_type') !== Promotion::DISCOUNT_PERCENTAGE) {
                $fail('Mức giảm tối đa chỉ áp dụng khi loại giảm là phần trăm.');
            }
        };
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
