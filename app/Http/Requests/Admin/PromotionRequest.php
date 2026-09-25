<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('promotion') ?? $this->route('id') ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:promotions,code,' . ($id ?? '')],
            'discount' => ['required', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:start_date', 'after_or_equal:today'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
