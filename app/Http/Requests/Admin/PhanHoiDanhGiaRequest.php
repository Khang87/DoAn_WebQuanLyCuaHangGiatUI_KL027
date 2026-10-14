<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PhanHoiDanhGiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $shopResponse = $this->input('shop_response');

        if (\is_string($shopResponse)) {
            $this->merge(['shop_response' => trim($shopResponse)]);
        }
    }

    public function rules(): array
    {
        return [
            'shop_response' => ['required', 'string', 'max:2000'],
        ];
    }
}
