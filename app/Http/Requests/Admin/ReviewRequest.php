<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('review') ?? $this->route('id') ?? null;

        return [
            'order_id' => ['required', 'exists:orders,id', 'unique:reviews,order_id,' . ($id ?? '')],
            'customer_id' => ['required', 'exists:customers,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:500'],
            'shop_response' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:'.implode(',', ReviewStatus::values())],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'ÄÆ¡n hÃ ng lÃ  báº¯t buá»™c.',
            'order_id.exists' => 'ÄÆ¡n hÃ ng khÃ´ng tá»“n táº¡i.',
            'order_id.unique' => 'ÄÆ¡n hÃ ng nÃ y Ä‘Ã£ Ä‘Æ°á»£c Ä‘Ã¡nh giÃ¡.',
            'customer_id.required' => 'KhÃ¡ch hÃ ng lÃ  báº¯t buá»™c.',
            'customer_id.exists' => 'KhÃ¡ch hÃ ng khÃ´ng tá»“n táº¡i.',
            'rating.required' => 'ÄÃ¡nh giÃ¡ sao lÃ  báº¯t buá»™c.',
            'rating.integer' => 'ÄÃ¡nh giÃ¡ sao pháº£i lÃ  sá»‘ nguyÃªn.',
            'rating.between' => 'ÄÃ¡nh giÃ¡ sao pháº£i tá»« 1 Ä‘áº¿n 5.',
            'content.max' => 'Ná»™i dung khÃ´ng quÃ¡ 2000 kÃ½ tá»±.',
            'images.array' => 'HÃ¬nh áº£nh pháº£i lÃ  máº£ng.',
            'images.*.string' => 'Má»—i hÃ¬nh áº£nh pháº£i lÃ  chuá»—i.',
            'images.*.max' => 'ÄÆ°á»ng dáº«n hÃ¬nh áº£nh khÃ´ng quÃ¡ 500 kÃ½ tá»±.',
            'shop_response.max' => 'Pháº£n há»“i cá»­a hÃ ng khÃ´ng quÃ¡ 2000 kÃ½ tá»±.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
        ];
    }
}