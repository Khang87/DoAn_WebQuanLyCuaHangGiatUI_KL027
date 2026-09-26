<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('customer') ?? $this->route('id') ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:customers,email,' . ($id ?? '')],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'points' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Họ tên là bắt buộc.',
            'name.string' => 'Họ tên phải là chuỗi ký tự.',
            'name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'email.email' => 'Định dạng email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại.',
            'phone.max' => 'Số điện thoại không được vượt quá 30 ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'points.integer' => 'Điểm phải là số nguyên.',
            'points.min' => 'Điểm không được nhỏ hơn 0.',
        ];
    }
}