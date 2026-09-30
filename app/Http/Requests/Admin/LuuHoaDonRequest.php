<?php

namespace App\Http\Requests\Admin;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuHoaDonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:DonHang,DonHangID'],
            'invoice_date' => ['nullable', 'date'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:'.implode(',', InvoiceStatus::values())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'ÄÆ¡n hÃ ng lÃ  báº¯t buá»™c.',
            'order_id.exists' => 'ÄÆ¡n hÃ ng khÃ´ng tá»“n táº¡i.',
            'total.numeric' => 'Tá»•ng tiá»n pháº£i lÃ  sá»‘.',
            'total.min' => 'Tá»•ng tiá»n khÃ´ng Ä‘Æ°á»£c nhá» hÆ¡n 0.',
            'status.required' => 'Tráº¡ng thÃ¡i lÃ  báº¯t buá»™c.',
            'status.in' => 'Tráº¡ng thÃ¡i khÃ´ng há»£p lá»‡.',
            'notes.max' => 'KhÃ´ng quÃ¡ 1000 kÃ½ tá»±.',
        ];
    }
}
