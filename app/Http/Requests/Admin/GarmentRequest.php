<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;

class GarmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('garment') ?? $this->route('id') ?? null;

        return [
            'TenDichVu' => ['required', 'string', 'max:255', 'unique:DichVu,TenDichVu,' . ($id ?? '') . ',DichVuID'],
            'LoaiDichVuID' => ['nullable', 'integer', 'exists:LoaiDichVu,LoaiDichVuID'],
            'LoaiDoGiatID' => ['nullable', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'ThoiGianDuKien' => ['required', 'integer', 'min:1'],
            'MoTa' => ['nullable', 'string', 'max:1000'],
            'TrangThai' => ['required', 'in:'.implode(',', RecordStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'TenDichVu.required' => 'Tên dịch vụ là bắt buộc.',
            'TenDichVu.unique' => 'Tên dịch vụ đã tồn tại.',
            'ThoiGianDuKien.required' => 'Thời gian ước tính là bắt buộc.',
            'ThoiGianDuKien.integer' => 'Thời gian phải là số nguyên.',
            'ThoiGianDuKien.min' => 'Thời gian không được nhỏ hơn 1 phút.',
            'LoaiDichVuID.exists' => 'Danh mục dịch vụ không tồn tại.',
            'LoaiDoGiatID.exists' => 'Loại đồ giặt không tồn tại.',
            'MoTa.max' => 'Không quá 1000 ký tự.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
