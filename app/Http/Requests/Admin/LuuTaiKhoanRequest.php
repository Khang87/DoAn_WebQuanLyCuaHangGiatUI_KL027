<?php

namespace App\Http\Requests\Admin;

use App\Models\VaiTro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'phone' => $isCreate
                ? ['prohibited']
                : ['nullable', 'string', 'max:15', 'regex:/^0\d{9,10}$/'],
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
            $isCustomerRole = $this->input('role') === VaiTro::slugForName('Khách hàng');

            $rules['NhanVienID'] = $isCustomerRole
                ? ['prohibited']
                : ['required', 'integer', 'exists:NhanVien,NhanVienID,TrangThai,Hoạt động'];
            $rules['KhachHangID'] = $isCustomerRole
                ? ['required', 'integer', 'exists:KhachHang,KhachHangID,TrangThai,Hoạt động']
                : ['prohibited'];
        }

        return $rules;
    }

    public function after(): array
    {
        if (! $this->isMethod('post')) {
            return [];
        }

        return [
            function (Validator $validator): void {
                foreach (['NhanVienID', 'KhachHangID'] as $foreignKey) {
                    $profileId = $this->input($foreignKey);

                    if ($profileId !== null && DB::table('TaiKhoan')
                        ->where($foreignKey, $profileId)
                        ->exists()) {
                        $validator->errors()->add($foreignKey, 'Hồ sơ này đã được liên kết với một tài khoản khác.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Định dạng email không hợp lệ.',
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'email.max' => 'Email không được vượt quá 100 ký tự.',
            'phone.prohibited' => 'Số điện thoại được lấy từ hồ sơ đã liên kết.',
            'phone.regex' => 'Số điện thoại không đúng định dạng (ví dụ: 0901234567).',
            'password.required' => 'Mật khẩu là bắt buộc khi tạo tài khoản.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.mixedCase' => 'Mật khẩu phải chứa chữ in hoa và thường.',
            'password.numbers' => 'Mật khẩu phải chứa ít nhất một chữ số.',
            'password.symbols' => 'Mật khẩu phải chứa ít nhất một ký tự đặc biệt.',
            'role.required' => 'Vai trò là bắt buộc.',
            'role.in' => 'Vai trò không hợp lệ.',
            'NhanVienID.required' => 'Hãy chọn hồ sơ nhân viên cho vai trò nội bộ.',
            'NhanVienID.prohibited' => 'Vai trò Khách hàng không được liên kết với hồ sơ nhân viên.',
            'NhanVienID.exists' => 'Nhân viên được chọn không tồn tại.',
            'KhachHangID.required' => 'Hãy chọn hồ sơ khách hàng cho vai trò Khách hàng.',
            'KhachHangID.prohibited' => 'Vai trò nội bộ không được liên kết với hồ sơ khách hàng.',
            'KhachHangID.exists' => 'Khách hàng được chọn không tồn tại.',
        ];
    }
}
