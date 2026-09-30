<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuDonHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('order')?->DonHangID
            ?? $this->route('order')
            ?? $this->route('id')
            ?? null;

        return [
            'MaDonHang' => ['nullable', 'string', 'max:30', 'unique:DonHang,MaDonHang,'.($id ?? '')],
            'KhachHangID' => ['required', 'integer', 'exists:KhachHang,KhachHangID'],
            'NhanVienID' => ['nullable', 'integer', 'exists:NhanVien,NhanVienID'],
            'BookingID' => ['nullable', 'integer', 'exists:Booking,BookingID'],
            'KhuyenMaiID' => ['nullable', 'integer', 'exists:KhuyenMai,KhuyenMaiID'],
            'promotion_code' => ['nullable', 'string', 'max:50'],
            'DiemSuDung' => ['nullable', 'integer', 'min:0'],
            'PhiGiaoHang' => ['nullable', 'numeric', 'min:0'],
            'TrangThai' => ['required', 'in:'.implode(',', OrderStatus::values())],
            'GhiChu' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.DichVuID' => ['nullable', 'integer', 'exists:DichVu,DichVuID'],
            'items.*.LoaiDoGiatID' => ['nullable', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'items.*.DonViTinhID' => ['nullable', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'items.*.DonGia' => ['nullable', 'numeric', 'min:0'],
            'items.*.SoLuong' => ['nullable', 'numeric', 'min:0'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'min:0'],
            'items.*.GhiChu' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'KhachHangID.required' => 'Khách hàng là bắt buộc.',
            'KhachHangID.exists' => 'Khách hàng không tồn tại.',
            'NhanVienID.exists' => 'Nhân viên không tồn tại.',
            'KhuyenMaiID.exists' => 'Chương trình khuyến mãi không tồn tại.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
            'GhiChu.max' => 'Ghi chú không quá 500 ký tự.',
            'items.*.SoLuong.numeric' => 'Số lượng phải là số.',
            'items.*.SoLuong.min' => 'Số lượng không được nhỏ hơn 0.',
        ];
    }
}
