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
            'DonHangID' => ['required', 'integer', 'exists:DonHang,DonHangID'],
            'DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'DonGia' => ['required', 'numeric', 'min:0'],
            'SoLuong' => ['required', 'numeric', 'min:1'],
            'KhoiLuong' => ['nullable', 'regex:/^\d+(\.\d+)?$/', 'gt:0'],
            'GhiChu' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'DonHangID.required' => 'Đơn hàng là bắt buộc.',
            'DonHangID.exists' => 'Đơn hàng không tồn tại.',
            'DichVuID.required' => 'Dịch vụ là bắt buộc.',
            'DichVuID.exists' => 'Dịch vụ không tồn tại.',
            'LoaiDoGiatID.required' => 'Loại đồ giặt là bắt buộc.',
            'LoaiDoGiatID.exists' => 'Loại đồ giặt không tồn tại.',
            'DonViTinhID.required' => 'Đơn vị tính là bắt buộc.',
            'DonViTinhID.exists' => 'Đơn vị tính không tồn tại.',
            'DonGia.required' => 'Đơn giá là bắt buộc.',
            'DonGia.numeric' => 'Đơn giá phải là số.',
            'DonGia.min' => 'Đơn giá không được nhỏ hơn 0.',
            'SoLuong.required' => 'Số lượng là bắt buộc.',
            'SoLuong.numeric' => 'Số lượng phải là số.',
            'SoLuong.min' => 'Số lượng phải lớn hơn hoặc bằng 1.',
            'KhoiLuong.regex' => 'Khối lượng chỉ được nhập số dương và số thập phân (VD: 0.5, 1.2).',
            'KhoiLuong.gt' => 'Khối lượng phải lớn hơn 0.',
            'GhiChu.max' => 'Ghi chú không quá 500 ký tự.',
        ];
    }

    /**
     * Chuẩn bị dữ liệu trước khi validate: đảm bảo KhoiLuong là số nếu rỗng.
     */
    protected function prepareForValidation(): void
    {
        $weight = $this->input('KhoiLuong');

        if ($weight === '' || $weight === null) {
            $this->merge(['KhoiLuong' => 0]);
        }
    }
}
