<?php

namespace App\Http\Requests\Admin;

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
            'status' => ['required', 'in:pending,partial,paid,failed,refunded'],
        ];
    }
}
