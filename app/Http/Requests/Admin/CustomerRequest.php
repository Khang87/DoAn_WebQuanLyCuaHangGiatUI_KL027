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
            'HoTen' => ['required', 'string', 'max:255'],
            'Email' => ['nullable', 'email', 'max:255', 'unique:KhachHang,Email,' . ($id ?? '')],
            'SoDienThoai' => ['nullable', 'string', 'max:30'],
            'DiaChi' => ['nullable', 'string', 'max:500'],
            'DiemHienTai' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'HoTen.required' => 'Họ tên là bắt buộc.',
            'HoTen.string' => 'Họ tên phải là chuỗi ký tự.',
            'HoTen.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'Email.email' => 'Định dạng email không hợp lệ.',
            'Email.unique' => 'Email đã tồn tại.',
            'SoDienThoai.max' => 'Số điện thoại không được vượt quá 30 ký tự.',
            'DiaChi.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'DiemHienTai.integer' => 'Điểm phải là số nguyên.',
            'DiemHienTai.min' => 'Điểm không được nhỏ hơn 0.',
        ];
    }
}
