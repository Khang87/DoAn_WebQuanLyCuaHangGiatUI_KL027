<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('booking') ?? $this->route('id') ?? null;

        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'staff_id' => ['nullable', 'exists:users,id'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:'.implode(',', BookingStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'KhÃ¡ch hÃ ng lÃ  báº¯t buá»™c.',
            'customer_id.exists' => 'KhÃ¡ch hÃ ng khÃ´ng tá»“n táº¡i.',
            'staff_id.exists' => 'NhÃ¢n viÃªn khÃ´ng tá»“n táº¡i.',
            'method.required' => 'PhÆ°Æ¡ng thá»©c nháº­n/giao Ä‘á»“ lÃ  báº¯t buá»™c.',
            'method.in' => 'PhÆ°Æ¡ng thá»©c khÃ´ng há»£p lá»‡. Chá»‰ cháº¥p nháº­n: nhan_do, giao_do.',
            'scheduled_date.required' => 'NgÃ y dá»± kiáº¿n lÃ  báº¯t buá»™c.',
            'scheduled_time.required' => 'Giá» dá»± kiáº¿n lÃ  báº¯t buá»™c.',
            'scheduled_time.date_format' => 'Äá»‹nh dáº¡ng giá»: HH:MM.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
            'notes.max' => 'KhÃ´ng quÃ¡ 1000 kÃ½ tá»±.',
        ];
    }
}