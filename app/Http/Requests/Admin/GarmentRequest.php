<?php

namespace App\Http\Requests\Admin;

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
            'name' => ['required', 'string', 'max:255', 'unique:garments,name,' . ($id ?? '')],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'condition_note' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên loại đồ giặt là bắt buộc.',
            'name.unique' => 'Tên đã tồn tại.',
            'price.required' => 'Giá là bắt buộc.',
            'price.numeric' => 'Giá phải là số.',
            'price.min' => 'Giá không được nhỏ hơn 0.',
            'category.max' => 'Không quá 255 ký tự.',
            'condition_note.max' => 'Không quá 1000 ký tự.',
            'status.required' => 'Trạng thái bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
