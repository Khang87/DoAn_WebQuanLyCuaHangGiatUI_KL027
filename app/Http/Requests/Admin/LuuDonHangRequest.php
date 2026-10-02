<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\DonViTinh;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LuuDonHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $order = $this->route('order');
        $id = $order instanceof DonHang
            ? $order->getKey()
            : ($order ?? $this->route('id'));

        return [
            'MaDonHang' => ['nullable', 'string', 'max:30', Rule::unique('DonHang', 'MaDonHang')->ignore($id, 'DonHangID')],
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
            'items.*.DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'items.*.LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'items.*.DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'items.*.DonGia' => ['nullable', 'numeric', 'min:0'],
            'items.*.SoLuong' => ['nullable', 'numeric', 'gt:0'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'gt:0'],
            'items.*.GhiChu' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                if (! is_array($item) || $validator->errors()->has("items.{$index}.DonViTinhID")) {
                    continue;
                }

                $unit = DonViTinh::query()->find($item['DonViTinhID'] ?? null);
                if (! $unit) {
                    continue;
                }

                $hasQuantity = isset($item['SoLuong']) && $item['SoLuong'] !== '';
                $hasWeight = isset($item['KhoiLuong']) && $item['KhoiLuong'] !== '';
                $validInput = $unit->isWeightUnit()
                    ? $hasWeight && ! $hasQuantity
                    : $hasQuantity && ! $hasWeight;

                if (! $validInput) {
                    $validator->errors()->add(
                        "items.{$index}.SoLuong",
                        $unit->isWeightUnit()
                            ? 'Đơn vị tính theo kg chỉ được nhập khối lượng.'
                            : 'Đơn vị tính theo món chỉ được nhập số lượng.',
                    );
                }
            }
        });
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
            'items.*.DichVuID.required' => 'Hãy chọn dịch vụ cho từng mặt hàng.',
            'items.*.LoaiDoGiatID.required' => 'Hãy chọn loại đồ giặt cho từng mặt hàng.',
            'items.*.DonViTinhID.required' => 'Chưa xác định được đơn vị tính của mặt hàng.',
            'items.*.SoLuong.numeric' => 'Số lượng phải là số.',
            'items.*.SoLuong.gt' => 'Số lượng phải lớn hơn 0.',
            'items.*.KhoiLuong.gt' => 'Khối lượng phải lớn hơn 0.',
        ];
    }
}
