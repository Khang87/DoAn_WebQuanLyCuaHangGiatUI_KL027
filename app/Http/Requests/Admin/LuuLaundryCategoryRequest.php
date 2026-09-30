<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuLaundryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeCategory = $this->route('laundry_category') ?? $this->route('id');
        $categoryId = is_numeric($routeCategory) ? (int) $routeCategory : null;

        return [
            'TenLoaiDichVu' => [
                'required',
                'string',
                'max:100',
                Rule::unique('LoaiDichVu', 'TenLoaiDichVu')->ignore($categoryId, 'LoaiDichVuID'),
            ],
            'MoTa' => ['nullable', 'string', 'max:255'],
            'TrangThai' => ['required', Rule::in(RecordStatus::databaseValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'TenLoaiDichVu.required' => 'Tên danh mục là bắt buộc.',
            'TenLoaiDichVu.unique' => 'Tên danh mục đã tồn tại.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
