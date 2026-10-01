<?php

namespace App\Http\Requests\Admin;

use App\Models\KhuyenMai;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuKhuyenMaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promotion = $this->route('promotion') ?? $this->route('id');
        $uniqueCode = Rule::unique('KhuyenMai', 'MaKhuyenMai');

        if ($promotion instanceof KhuyenMai) {
            $uniqueCode->ignore($promotion->KhuyenMaiID, 'KhuyenMaiID');
        } elseif (is_numeric($promotion)) {
            $uniqueCode->ignore((int) $promotion, 'KhuyenMaiID');
        }

        return [
            'TenKhuyenMai' => ['required', 'string', 'min:3', 'max:150'],
            'MaKhuyenMai' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                $uniqueCode,
            ],
            'LoaiKhuyenMai' => ['required', Rule::in(array_keys(KhuyenMai::discountTypeOptions()))],
            'GiaTriGiam' => ['required', 'numeric', 'min:0', $this->discountValueWithinType()],
            'GiaTriDonToiThieu' => ['nullable', 'numeric', 'min:0'],
            'MucGiamToiDa' => ['nullable', 'numeric', 'min:0', $this->maxDiscountOnlyForPercentage()],
            'DieuKienApDung' => ['nullable', 'string', 'max:500'],
            'NgayBatDau' => ['required', 'date'],
            'NgayKetThuc' => ['required', 'date', 'after_or_equal:NgayBatDau'],
            'TrangThai' => ['required', Rule::in(['Hoạt động', 'Tạm ngưng', 'Hết hạn'])],
        ];
    }

    public function messages(): array
    {
        return [
            'TenKhuyenMai.min' => 'Tên chương trình phải có ít nhất 3 ký tự.',
            'TenKhuyenMai.max' => 'Tên chương trình không được vượt quá 150 ký tự.',
            'MaKhuyenMai.min' => 'Mã khuyến mãi phải có ít nhất 3 ký tự.',
            'MaKhuyenMai.regex' => 'Mã khuyến mãi chỉ gồm chữ, số, gạch ngang và gạch dưới.',
            'MaKhuyenMai.unique' => 'Mã khuyến mãi đã tồn tại.',
            'LoaiKhuyenMai.required' => 'Loại giảm là bắt buộc.',
            'LoaiKhuyenMai.in' => 'Loại giảm không hợp lệ.',
            'GiaTriGiam.required' => 'Giá trị giảm là bắt buộc.',
            'GiaTriGiam.numeric' => 'Giá trị giảm phải là số.',
            'GiaTriGiam.min' => 'Giá trị giảm không được nhỏ hơn 0.',
            'GiaTriDonToiThieu.numeric' => 'Đơn hàng tối thiểu phải là số.',
            'MucGiamToiDa.numeric' => 'Mức giảm tối đa phải là số.',
            'NgayBatDau.required' => 'Ngày bắt đầu là bắt buộc.',
            'NgayKetThuc.required' => 'Ngày kết thúc là bắt buộc.',
            'NgayKetThuc.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
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

            $type = $this->input('LoaiKhuyenMai');

            if ($type === KhuyenMai::DISCOUNT_PERCENTAGE && (float) $value > 100) {
                $fail('Giá trị giảm theo phần trăm không được vượt quá 100%.');
            }
            if ($type === KhuyenMai::DISCOUNT_FIXED && (float) $value < 1000) {
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

            if ($this->input('LoaiKhuyenMai') !== KhuyenMai::DISCOUNT_PERCENTAGE) {
                $fail('Mức giảm tối đa chỉ áp dụng khi loại giảm là phần trăm.');
            }
        };
    }
}
