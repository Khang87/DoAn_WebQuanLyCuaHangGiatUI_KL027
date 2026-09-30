<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;

class LuuThanhToanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'required_without:invoice_id', 'exists:DonHang,DonHangID'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer'],
            'invoice_id' => ['nullable', 'required_without:order_id', 'integer', 'exists:HoaDon,HoaDonID'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['required', 'in:'.implode(',', PaymentStatus::values())],
            'transaction_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
