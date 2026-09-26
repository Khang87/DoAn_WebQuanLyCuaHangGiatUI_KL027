<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
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
            'status' => ['required', 'in:'.implode(',', RecordStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'garment_id.required' => 'Loáº¡i Ä‘á»“ giáº·t lÃ  báº¯t buá»™c.',
            'garment_id.exists' => 'Loáº¡i Ä‘á»“ giáº·t khÃ´ng tá»“n táº¡i.',
            'condition_type.required' => 'Loáº¡i hiá»‡n tráº¡ng lÃ  báº¯t buá»™c.',
            'condition_type.max' => 'KhÃ´ng quÃ¡ 100 kÃ½ tá»±.',
            'photo.max' => 'ÄÆ°á»ng dáº«n áº£nh khÃ´ng quÃ¡ 500 kÃ½ tá»±.',
            'status.required' => 'Tráº¡ng thÃ¡i báº¯t buá»™c.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
        ];
    }
}
