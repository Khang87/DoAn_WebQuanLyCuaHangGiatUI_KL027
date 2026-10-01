<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuBangGiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'DonGia' => ['required', 'numeric', 'min:0'],
            'NgayApDung' => ['required', 'date'],
            'NgayKetThuc' => ['nullable', 'date', 'after_or_equal:NgayApDung'],
            'TrangThai' => [
                'required',
                Rule::in(['Hoạt động', 'Hết hiệu lực', 'Tạm ngưng']),
            ],
        ];
    }
}
