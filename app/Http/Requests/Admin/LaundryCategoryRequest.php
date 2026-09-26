<?php

namespace App\Http\Requests\Admin;

use App\Models\LaundryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaundryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeCategory = $this->route('laundry_category') ?? $this->route('id');

        $categoryId = $routeCategory instanceof LaundryCategory
            ? $routeCategory->getKey()
            : (is_numeric($routeCategory) ? (int) $routeCategory : null);

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('laundry_categories', 'code')->ignore($categoryId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique('laundry_categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã danh mục là bắt buộc.',
            'code.unique' => 'Mã danh mục đã tồn tại.',
            'name.required' => 'Tên danh mục là bắt buộc.',
            'slug.required' => 'Slug là bắt buộc.',
            'slug.unique' => 'Slug đã tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}