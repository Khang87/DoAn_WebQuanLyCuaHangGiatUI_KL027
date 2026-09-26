<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
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
            'status' => ['required', 'in:'.implode(',', RecordStatus::values())],
            'description' => ['nullable', 'string', 'max:1000'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'TÃªn dá»‹ch vá»¥ lÃ  báº¯t buá»™c.',
            'name.unique' => 'TÃªn dá»‹ch vá»¥ Ä‘Ã£ tá»“n táº¡i.',
            'price.required' => 'GiÃ¡ lÃ  báº¯t buá»™c.',
            'price.numeric' => 'GiÃ¡ pháº£i lÃ  sá»‘.',
            'price.min' => 'GiÃ¡ khÃ´ng Ä‘Æ°á»£c nhá» hÆ¡n 0.',
            'unit.required' => 'ÄÆ¡n giÃ¡ lÃ  báº¯t buá»™c.',
            'unit.max' => 'ÄÆ¡n giÃ¡ khÃ´ng Ä‘Æ°á»£c quÃ¡ 20 kÃ½ tá»±.',
            'status.required' => 'Tráº¡ng thÃ¡i lÃ  báº¯t buá»™c.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
            'service_category_id.exists' => 'Danh má»¥c khÃ´ng tá»“n táº¡i.',
            'processing_time.integer' => 'Thá»i gian pháº£i lÃ  sá»‘ nguyÃªn.',
            'processing_time.min' => 'Thá»i gian khÃ´ng Ä‘Æ°á»£c nhá» hÆ¡n 0.',
            'icon.max' => 'Icon khÃ´ng Ä‘Æ°á»£c quÃ¡ 100 kÃ½ tá»±.',
        ];
    }
}
