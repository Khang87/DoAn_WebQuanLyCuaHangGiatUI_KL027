<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GarmentConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('garment_condition') ?? $this->route('id') ?? null;

        return [
            'garment_id' => ['required', 'exists:garments,id'],
            'condition_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'garment_id.required' => 'Loại đồ giặt là bắt buộc.',
            'garment_id.exists' => 'Loại đồ giặt không tồn tại.',
            'condition_type.required' => 'Loại hiện trạng là bắt buộc.',
            'condition_type.max' => 'Không quá 100 ký tự.',
            'photo.max' => 'Đường dẫn ảnh không quá 500 ký tự.',
            'status.required' => 'Trạng thái bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
