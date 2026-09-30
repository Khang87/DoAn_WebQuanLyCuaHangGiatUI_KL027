<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuGarmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('garment') ?? $this->route('id');
        $id = is_numeric($id) ? (int) $id : null;

        return [
            'TenDichVu' => [
                'required',
                'string',
                'max:150',
                Rule::unique('DichVu', 'TenDichVu')->ignore($id, 'DichVuID'),
            ],
            'LoaiDichVuID' => ['required', 'integer', 'exists:LoaiDichVu,LoaiDichVuID'],
            'ThoiGianDuKien' => ['required', 'integer', 'min:1'],
            'MoTa' => ['nullable', 'string', 'max:500'],
            'TrangThai' => ['required', 'in:'.implode(',', RecordStatus::databaseValues())],
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
            'LoaiDichVuID.required' => 'Danh mục dịch vụ là bắt buộc.',
            'MoTa.max' => 'Không quá 1000 ký tự.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
