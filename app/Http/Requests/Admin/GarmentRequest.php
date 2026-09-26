<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
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
            'status' => ['required', 'in:'.implode(',', RecordStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'TÃªn loáº¡i Ä‘á»“ giáº·t lÃ  báº¯t buá»™c.',
            'name.unique' => 'TÃªn Ä‘Ã£ tá»“n táº¡i.',
            'price.required' => 'GiÃ¡ lÃ  báº¯t buá»™c.',
            'price.numeric' => 'GiÃ¡ pháº£i lÃ  sá»‘.',
            'price.min' => 'GiÃ¡ khÃ´ng Ä‘Æ°á»£c nhá» hÆ¡n 0.',
            'category.max' => 'KhÃ´ng quÃ¡ 255 kÃ½ tá»±.',
            'condition_note.max' => 'KhÃ´ng quÃ¡ 1000 kÃ½ tá»±.',
            'status.required' => 'Tráº¡ng thÃ¡i báº¯t buá»™c.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
        ];
    }
}
