<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingMethod;
use App\Enums\BookingStatus;
use App\Models\DonViTinh;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LuuBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:KhachHang,KhachHangID'],
            'staff_id' => ['nullable', 'exists:NhanVien,NhanVienID'],
            'method' => ['required', 'in:'.implode(',', BookingMethod::values())],
            'address' => ['nullable', 'string', 'max:255', 'required_if:method,'.BookingMethod::GiaoDo->value],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:'.implode(',', BookingStatus::values())],
            'items' => ['sometimes', 'array'],
            'items.*.DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'items.*.LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'items.*.DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'items.*.SoLuong' => ['nullable', 'numeric', 'gt:0'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'gt:0'],
            'items.*.GhiChu' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (! is_array($items)) {
            return;
        }

        $items = array_values(array_filter($items, function (mixed $item): bool {
            if (! is_array($item)) {
                return true;
            }

            return collect($item)->contains(fn (mixed $value): bool => $value !== null && $value !== '');
        }));

        $this->merge(['items' => $items]);
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

                $quantity = $item['SoLuong'] ?? null;
                $weight = $item['KhoiLuong'] ?? null;
                $hasQuantity = $quantity !== null && $quantity !== '';
                $hasWeight = $weight !== null && $weight !== '';

                if ($unit->isWeightUnit()) {
                    if ($hasQuantity || ! $hasWeight) {
                        $validator->errors()->add(
                            "items.{$index}.SoLuong",
                            'Đơn vị tính theo kg chỉ được nhập khối lượng, không nhập số lượng.',
                        );
                    }

                    continue;
                }

                if ($hasWeight || ! $hasQuantity) {
                    $validator->errors()->add(
                        "items.{$index}.SoLuong",
                        'Đơn vị tính theo món chỉ được nhập số lượng, không nhập khối lượng.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'staff_id.exists' => 'Nhân viên không tồn tại.',
            'method.required' => 'Phương thức nhận/giao đồ là bắt buộc.',
            'method.in' => 'Phương thức không hợp lệ. Chỉ chấp nhận: '.implode(', ', BookingMethod::values()).'.',
            'address.required_if' => 'Địa chỉ nhận đồ là bắt buộc khi chọn giao nhận tại nhà.',
            'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'scheduled_date.required' => 'Ngày dự kiến là bắt buộc.',
            'scheduled_time.required' => 'Giờ dự kiến là bắt buộc.',
            'scheduled_time.date_format' => 'Định dạng giờ: HH:MM.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Không quá 500 ký tự.',
            'items.*.DichVuID.required' => 'Hãy chọn dịch vụ cho từng dòng đặt lịch.',
            'items.*.LoaiDoGiatID.required' => 'Hãy chọn loại đồ giặt cho từng dòng đặt lịch.',
            'items.*.DonViTinhID.required' => 'Hãy chọn đơn vị tính cho từng dòng đặt lịch.',
            'items.*.SoLuong.gt' => 'Số lượng phải lớn hơn 0.',
            'items.*.KhoiLuong.gt' => 'Khối lượng phải lớn hơn 0.',
        ];
    }
}
