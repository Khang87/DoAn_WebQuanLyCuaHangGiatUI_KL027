<?php

namespace App\Http\Requests\Admin;

class BookingQuoteRefusalRequest extends XacNhanBookingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['customer_accepted']);
        $rules['rejection_reason'] = ['required', 'string', 'max:500'];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'rejection_reason.required' => 'Vui lòng nhập lý do khách hàng từ chối báo giá.',
            'rejection_reason.max' => 'Lý do từ chối không được vượt quá 500 ký tự.',
        ]);
    }
}
