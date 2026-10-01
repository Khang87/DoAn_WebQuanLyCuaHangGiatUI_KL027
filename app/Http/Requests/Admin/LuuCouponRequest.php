<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('coupon') ?? $this->route('id');
        $uniqueCode = Rule::unique('KhuyenMai', 'MaKhuyenMai');

        if (is_numeric($id)) {
            $uniqueCode->ignore((int) $id, 'KhuyenMaiID');
        }

        return [
            'MaKhuyenMai' => ['required', 'string', 'max:50', $uniqueCode],
            'TenKhuyenMai' => ['required', 'string', 'max:150'],
            'LoaiKhuyenMai' => ['required', Rule::in(['Phần trăm', 'Tiền mặt'])],
            'GiaTriGiam' => ['required', 'numeric', 'min:0'],
            'GiaTriDonToiThieu' => ['nullable', 'numeric', 'min:0'],
            'MucGiamToiDa' => ['nullable', 'numeric', 'min:0'],
            'DieuKienApDung' => ['nullable', 'string', 'max:500'],
            'NgayBatDau' => ['required', 'date'],
            'NgayKetThuc' => ['required', 'date', 'after_or_equal:NgayBatDau'],
            'TrangThai' => ['required', Rule::in(['Hoạt động', 'Tạm ngưng', 'Hết hạn'])],
        ];
    }

    public function messages(): array
    {
        return [
            'MaKhuyenMai.required' => 'Mã coupon là bắt buộc.',
            'MaKhuyenMai.unique' => 'Mã coupon đã tồn tại.',
            'TenKhuyenMai.required' => 'Tên khuyến mãi là bắt buộc.',
            'LoaiKhuyenMai.required' => 'Loại giảm giá là bắt buộc.',
            'LoaiKhuyenMai.in' => 'Loại giảm giá không hợp lệ.',
            'GiaTriGiam.required' => 'Giá trị giảm giá là bắt buộc.',
            'GiaTriGiam.numeric' => 'Giá trị phải là số.',
            'SoLuongSuDung.integer' => 'Số lượng phải là số nguyên.',
            'NgayBatDau.required' => 'Ngày bắt đầu là bắt buộc.',
            'NgayKetThuc.required' => 'Ngày kết thúc là bắt buộc.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
        ];
    }
}
