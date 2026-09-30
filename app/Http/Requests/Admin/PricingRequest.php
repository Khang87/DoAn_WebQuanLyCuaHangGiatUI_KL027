<?php

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;

class PricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('pricing') ?? $this->route('id') ?? null;

        return [
            'service_id' => ['nullable', 'exists:DichVu,DichVuID'],
            'garment_id' => ['nullable', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'price' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:'.implode(',', RecordStatus::values())],
            'description' => ['nullable', 'string'],
        ];
    }
}