<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuDanhMucLoaiDoGiatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'TenDanhMuc' => ['required', 'string', 'max:100'],
            'MoTa' => ['nullable', 'string'],
            'TrangThai' => ['required', 'in:'.implode(',', RecordStatus::databaseValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'TenDanhMuc.required' => 'Tên danh mục là bắt buộc.',
            'TenDanhMuc.max' => 'Tên danh mục không được vượt quá 100 ký tự.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
