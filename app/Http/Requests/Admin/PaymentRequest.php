<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,bank_transfer,momo,credit_card,e_wallet'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['required', 'in:'.implode(',', PaymentStatus::values())],
            'transaction_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}