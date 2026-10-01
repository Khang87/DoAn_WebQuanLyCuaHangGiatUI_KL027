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
            'service_id' => ['nullable', 'integer', 'exists:DichVu,DichVuID', 'required_with:garment_id,unit_id,quantity,weight'],
            'garment_id' => ['nullable', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID', 'required_with:service_id,unit_id,quantity,weight'],
            'unit_id' => ['nullable', 'integer', 'exists:DonViTinh,DonViTinhID', 'required_with:service_id,garment_id,quantity,weight'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'weight' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['service_id', 'garment_id', 'unit_id'])) {
                return;
            }

            $hasSnapshotInput = collect(['service_id', 'garment_id', 'unit_id', 'quantity', 'weight'])
                ->contains(fn (string $field): bool => $this->input($field) !== null && $this->input($field) !== '');

            if (! $hasSnapshotInput) {
                return;
            }

            $unit = DonViTinh::query()->find($this->input('unit_id'));
            if (! $unit) {
                return;
            }

            $quantity = $this->input('quantity');
            $weight = $this->input('weight');

            if ($unit->isWeightUnit()) {
                if ($quantity !== null && $quantity !== '') {
                    $validator->errors()->add('quantity', 'Đơn vị tính theo kg không được nhập số lượng.');
                }

                if ($weight === null || $weight === '') {
                    $validator->errors()->add('weight', 'Vui lòng nhập khối lượng cho đơn vị tính theo kg.');
                }

                return;
            }

            if ($weight !== null && $weight !== '') {
                $validator->errors()->add('weight', 'Đơn vị tính theo món không được nhập khối lượng.');
            }

            if ($quantity === null || $quantity === '') {
                $validator->errors()->add('quantity', 'Vui lòng nhập số lượng cho đơn vị tính này.');
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
            'service_id.required_with' => 'Hãy chọn đầy đủ dịch vụ và thông tin dòng hàng.',
            'garment_id.required_with' => 'Hãy chọn đầy đủ dịch vụ và thông tin dòng hàng.',
            'unit_id.required_with' => 'Hãy chọn đơn vị tính cho dòng hàng.',
            'quantity.gt' => 'Số lượng phải lớn hơn 0.',
            'weight.gt' => 'Khối lượng phải lớn hơn 0.',
        ];
    }
}
