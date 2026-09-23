<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('service') ?? $this->route('id') ?? null;

        return [
            'name' => ['required', 'string', 'max:255', 'unique:services,name,' . ($id ?? '')],
            'type' => ['nullable', 'string', 'max:50'],
            'processing_time' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'status' => ['required', 'in:active,inactive'],
            'description' => ['nullable', 'string', 'max:1000'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'name.unique' => 'Tên dịch vụ đã tồn tại.',
            'price.required' => 'Giá là bắt buộc.',
            'price.numeric' => 'Giá phải là số.',
            'price.min' => 'Giá không được nhỏ hơn 0.',
            'unit.required' => 'Đơn giá là bắt buộc.',
            'unit.max' => 'Đơn giá không được quá 20 ký tự.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'service_category_id.exists' => 'Danh mục không tồn tại.',
            'processing_time.integer' => 'Thời gian phải là số nguyên.',
            'processing_time.min' => 'Thời gian không được nhỏ hơn 0.',
            'icon.max' => 'Icon không được quá 100 ký tự.',
        ];
    }
}
