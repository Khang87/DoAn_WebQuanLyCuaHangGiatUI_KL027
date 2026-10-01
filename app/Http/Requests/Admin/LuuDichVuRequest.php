<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuDichVuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('service') ?? $this->route('id') ?? null;
        $uniqueName = Rule::unique('DichVu', 'TenDichVu');

        if ($id !== null) {
            $uniqueName->ignore($id, 'DichVuID');
        }

        return [
            'TenDichVu' => ['required', 'string', 'min:3', 'max:150', $uniqueName],
            'LoaiDichVuID' => ['required', 'integer', 'exists:LoaiDichVu,LoaiDichVuID'],
            'MoTa' => ['nullable', 'string', 'max:500'],
            'ThoiGianDuKien' => ['nullable', 'integer', 'min:0'],
            'TrangThai' => ['required', Rule::in(RecordStatus::databaseValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'TenDichVu.required' => 'Tên dịch vụ là bắt buộc.',
            'TenDichVu.min' => 'Tên dịch vụ phải có ít nhất 3 ký tự.',
            'TenDichVu.max' => 'Tên dịch vụ không được vượt quá 150 ký tự.',
            'TenDichVu.unique' => 'Tên dịch vụ đã tồn tại.',
            'LoaiDichVuID.required' => 'Danh mục dịch vụ là bắt buộc.',
            'LoaiDichVuID.exists' => 'Danh mục dịch vụ không tồn tại.',
            'MoTa.max' => 'Mô tả không được vượt quá 500 ký tự.',
            'ThoiGianDuKien.integer' => 'Thời gian dự kiến phải là số nguyên.',
            'ThoiGianDuKien.min' => 'Thời gian dự kiến không được nhỏ hơn 0.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
