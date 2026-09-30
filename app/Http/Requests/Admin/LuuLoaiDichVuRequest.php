<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuLoaiDichVuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'TenLoaiDichVu' => ['required', 'string', 'max:100'],
            'MoTa' => ['nullable', 'string', 'max:255'],
            'TrangThai' => ['required', 'in:'.implode(',', RecordStatus::databaseValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'TenLoaiDichVu.required' => 'Tên danh mục là bắt buộc.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
