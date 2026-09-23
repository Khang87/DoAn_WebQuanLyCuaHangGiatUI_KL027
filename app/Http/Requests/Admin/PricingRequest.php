<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'price' => 'required|numeric|min:0',
            'status' => 'nullable|string',
            'description' => 'nullable|string',
        ];
    }
}
