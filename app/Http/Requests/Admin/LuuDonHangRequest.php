<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Models\DonHang;
use App\Models\DonViTinh;
use App\Models\KhuyenMai;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LuuDonHangRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $orderCode = $this->input('MaDonHang');
        if (is_string($orderCode)) {
            $this->merge(['MaDonHang' => trim($orderCode) ?: null]);
        }

        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as &$item) {
            if (is_array($item) && isset($item['KhoiLuong']) && is_numeric($item['KhoiLuong'])) {
                $item['KhoiLuong'] = round((float) $item['KhoiLuong'], 2);
            }
        }
        unset($item);

        $this->merge(['items' => $items]);
    }

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
            'NhanVienID' => ['required', 'integer', 'exists:NhanVien,NhanVienID'],
            'BookingID' => ['nullable', 'integer', 'exists:Booking,BookingID'],
            'KhuyenMaiID' => ['nullable', 'integer', 'exists:KhuyenMai,KhuyenMaiID'],
            'promotion_code' => ['nullable', 'string', 'max:50'],
            'DiemSuDung' => ['nullable', 'integer', 'min:0'],
            'use_points' => ['nullable', 'boolean'],
            'PhiGiaoHang' => ['nullable', 'numeric', 'min:0'],
            'TrangThai' => ['required', 'in:'.implode(',', OrderStatus::values())],
            'cancellation_reason' => ['nullable', 'required_if:TrangThai,Đã hủy', 'string', 'max:500'],
            'GhiChu' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'items.*.LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'items.*.DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'items.*.DonGia' => ['nullable', 'numeric', 'min:0'],
            'items.*.SoLuong' => ['nullable', 'integer', 'min:1'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'min:0'],
            'items.*.GhiChu' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $promotionCode = trim((string) $this->input('promotion_code', ''));
            if ($promotionCode !== '') {
                $promotion = KhuyenMai::findByCode($promotionCode);
                if (! $promotion) {
                    $validator->errors()->add('promotion_code', 'Mã khuyến mãi không tồn tại.');
                } elseif (
                    $this->filled('KhuyenMaiID')
                    && (int) $this->input('KhuyenMaiID') !== (int) $promotion->KhuyenMaiID
                ) {
                    $validator->errors()->add('KhuyenMaiID', 'Mã voucher không khớp với chương trình đã chọn.');
                }
            }

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
                    ? $hasWeight && is_numeric($item['KhoiLuong']) && (float) $item['KhoiLuong'] > 0 && ! $hasQuantity
                    : $hasQuantity
                        && is_numeric($item['SoLuong'])
                        && (float) $item['SoLuong'] >= 1
                        && floor((float) $item['SoLuong']) === (float) $item['SoLuong']
                        && (! $hasWeight || (is_numeric($item['KhoiLuong']) && (float) $item['KhoiLuong'] === 0.0));

                if (! $validInput) {
                    $validator->errors()->add(
                        "items.{$index}.".($unit->isWeightUnit() ? 'SoLuong' : 'KhoiLuong'),
                        $unit->isWeightUnit()
                            ? 'Đơn vị tính theo kg chỉ được nhập khối lượng lớn hơn 0; số lượng món không được lưu cho dòng này.'
                            : 'Đơn vị tính theo món cần số lượng nguyên dương; khối lượng phải bằng 0 hoặc để trống.',
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
            'NhanVienID.required' => 'Vui lòng chọn nhân viên phụ trách.',
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
            'items.*.SoLuong.integer' => 'Số lượng món phải là số nguyên.',
            'items.*.SoLuong.min' => 'Số lượng phải từ 1 món trở lên.',
            'items.*.KhoiLuong.gt' => 'Khối lượng phải lớn hơn 0.',
            'items.*.KhoiLuong.min' => 'Khối lượng không được nhỏ hơn 0.',
        ];
    }
}
