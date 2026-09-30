<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuGarmentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('garment_category') ?? $this->route('id');
        $id = is_numeric($id) ? (int) $id : null;

        return [
            'TenLoaiDoGiat' => [
                'required',
                'string',
                'max:150',
                Rule::unique('LoaiDoGiat', 'TenLoaiDoGiat')->ignore($id, 'LoaiDoGiatID'),
            ],
            'MoTa' => ['nullable', 'string', 'max:255'],
            'TrangThai' => ['required', 'in:'.implode(',', RecordStatus::databaseValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'TenLoaiDoGiat.required' => 'Tên loại đồ giặt là bắt buộc.',
            'TenLoaiDoGiat.unique' => 'Tên loại đồ giặt đã tồn tại.',
            'MoTa.max' => 'Mô tả không được vượt quá 255 ký tự.',
            'TrangThai.required' => 'Trạng thái là bắt buộc.',
            'TrangThai.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
