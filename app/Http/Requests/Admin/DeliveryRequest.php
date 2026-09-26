<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('delivery') ?? $this->route('id') ?? null;

        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'order_id' => ['required', 'exists:orders,id'],
            'employee_id' => ['nullable', 'exists:users,id'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'address' => ['nullable', 'string', 'max:500'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', 'in:'.implode(',', DeliveryStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'KhÃ¡ch hÃ ng lÃ  báº¯t buá»™c.',
            'customer_id.exists' => 'KhÃ¡ch hÃ ng khÃ´ng tá»“n táº¡i.',
            'order_id.required' => 'ÄÆ¡n hÃ ng lÃ  báº¯t buá»™c Ä‘á»ƒ táº¡o giao nháº­n.',
            'order_id.exists' => 'ÄÆ¡n hÃ ng khÃ´ng tá»“n táº¡i.',
            'employee_id.exists' => 'NhÃ¢n viÃªn khÃ´ng tá»“n táº¡i.',
            'method.required' => 'PhÆ°Æ¡ng thá»©c giao nháº­n lÃ  báº¯t buá»™c.',
            'method.in' => 'PhÆ°Æ¡ng thá»©c khÃ´ng há»£p lá»‡. Chá»‰ cháº¥p nháº­n: nhan_do, giao_do.',
            'pickup_date.required' => 'NgÃ y láº¥y hÃ ng lÃ  báº¯t buá»™c.',
            'pickup_time.required' => 'Giá» láº¥y hÃ ng lÃ  báº¯t buá»™c.',
            'pickup_time.date_format' => 'Äá»‹nh dáº¡ng giá»: HH:MM.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
            'address.max' => 'KhÃ´ng quÃ¡ 500 kÃ½ tá»±.',
            'notes.max' => 'KhÃ´ng quÃ¡ 1000 kÃ½ tá»±.',
        ];
    }
}