<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('coupon') ?? $this->route('id') ?? null;

        return [
            'MaKhuyenMai' => ['required', 'string', 'max:50', 'unique:KhuyenMai,MaKhuyenMai,'.($id ?? ''), 'KhuyenMaiID'],
            'TenKhuyenMai' => ['required', 'string', 'max:255'],
            'LoaiKhuyenMai' => ['required', 'in:Phần trăm,Tiền mặt'],
            'GiaTriGiam' => ['required', 'numeric', 'min:0'],
            'GiaTriDonToiThieu' => ['nullable', 'numeric', 'min:0'],
            'MucGiamToiDa' => ['nullable', 'numeric', 'min:0'],
            'SoLuongSuDung' => ['nullable', 'integer', 'min:0'],
            'DieuKienApDung' => ['nullable', 'string', 'max:255'],
            'NgayBatDau' => ['required', 'date'],
            'NgayKetThuc' => ['required', 'date'],
            'TrangThai' => ['required', 'in:'.implode(',', RecordStatus::values())],
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
