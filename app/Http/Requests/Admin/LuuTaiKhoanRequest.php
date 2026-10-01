<?php

namespace App\Http\Requests\Admin;

use App\Models\VaiTro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class LuuTaiKhoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('account') ?? $this->route('user') ?? null;
        $uniqueEmail = Rule::unique('TaiKhoan', 'Email');
        $uniqueUsername = Rule::unique('TaiKhoan', 'TenDangNhap');

        if (is_numeric($id)) {
            $uniqueEmail->ignore((int) $id, 'TaiKhoanID');
            $uniqueUsername->ignore((int) $id, 'TaiKhoanID');
        }

        $isCreate = $this->isMethod('post');

        $rules = [
            'email' => ['required', 'email', 'max:100', $uniqueEmail, $uniqueUsername],
            'phone' => ['nullable', 'string', 'max:15', 'regex:/^0\d{9,10}$/'],
            'password' => $isCreate
                ? ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]
                : ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => [
                'required',
                Rule::in(
                    VaiTro::query()
                        ->where('TrangThai', 'Hoạt động')
                        ->get()
                        ->map(fn (VaiTro $role): string => $role->slug)
                        ->all(),
                ),
            ],
        ];

        if ($isCreate) {
            $rules['NhanVienID'] = ['nullable', 'required_without:KhachHangID', 'prohibits:KhachHangID', 'integer', 'exists:NhanVien,NhanVienID'];
            $rules['KhachHangID'] = ['nullable', 'required_without:NhanVienID', 'prohibits:NhanVienID', 'integer', 'exists:KhachHang,KhachHangID'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Định dạng email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'email.max' => 'Email không được vượt quá 100 ký tự.',
            'phone.regex' => 'Số điện thoại không đúng định dạng (ví dụ: 0901234567).',
            'password.required' => 'Mật khẩu là bắt buộc khi tạo tài khoản.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.mixedCase' => 'Mật khẩu phải chứa chữ in hoa và thường.',
            'password.numbers' => 'Mật khẩu phải chứa ít nhất một chữ số.',
            'password.symbols' => 'Mật khẩu phải chứa ít nhất một ký tự đặc biệt.',
            'role.required' => 'Vai trò là bắt buộc.',
            'role.in' => 'Vai trò không hợp lệ.',
            'NhanVienID.required_without' => 'Hãy liên kết tài khoản với một nhân viên hoặc một khách hàng.',
            'NhanVienID.prohibits' => 'Tài khoản chỉ được liên kết với nhân viên hoặc khách hàng, không được chọn cả hai.',
            'NhanVienID.exists' => 'Nhân viên được chọn không tồn tại.',
            'KhachHangID.required_without' => 'Hãy liên kết tài khoản với một nhân viên hoặc một khách hàng.',
            'KhachHangID.prohibits' => 'Tài khoản chỉ được liên kết với nhân viên hoặc khách hàng, không được chọn cả hai.',
            'KhachHangID.exists' => 'Khách hàng được chọn không tồn tại.',
        ];
    }
}
