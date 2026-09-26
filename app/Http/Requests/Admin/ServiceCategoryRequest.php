<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;

class ServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('service_category') ?? $this->route('id') ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:service_categories,slug,' . ($id ?? '')],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:'.implode(',', RecordStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'TÃªn danh má»¥c lÃ  báº¯t buá»™c.',
            'slug.required' => 'Slug lÃ  báº¯t buá»™c.',
            'slug.unique' => 'Slug Ä‘Ã£ tá»“n táº¡i.',
            'status.required' => 'Tráº¡ng thÃ¡i lÃ  báº¯t buá»™c.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
        ];
    }
}
