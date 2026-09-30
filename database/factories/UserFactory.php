<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Sinh dữ liệu cho bảng `TaiKhoan`.
 *
 * Vai trò không nằm trên bảng `TaiKhoan` mà nằm ở bảng nối `TaiKhoan_VaiTro`,
 * nên các state vai trò gán quan hệ sau khi tạo bản ghi.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Mật khẩu dùng chung cho mọi bản ghi sinh ra.
     */
    protected static ?string $password = null;

    /**
     * Người tạo dùng email làm khoá nhận diện, cần khác nhau giữa các bản ghi.
     *
     * @var list<string>
     */
    private const ROLE_LABELS = [
        'owner' => 'Chủ cửa hàng',
        'manager' => 'Quản lý',
        'staff' => 'Nhân viên',
        'customer' => 'Khách hàng',
    ];

    /**
     * Cột của bảng `TaiKhoan`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'TenDangNhap' => fake()->unique()->userName(),
            'Email' => fake()->unique()->safeEmail(),
            'MatKhau' => static::$password ??= Hash::make('password'),
            'SoDienThoai' => fake()->unique()->numerify('09########'),
            'TrangThai' => 'Hoạt động',
            'NgayTao' => now(),
        ];
    }

    /**
     * Gán một vai trò cho tài khoản vừa tạo.
     *
     * Bảng `TaiKhoan` không có cột `role`, nên phải ghi vào `TaiKhoan_VaiTro`.
     */
    private function attachRole(string $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug) {
            $role = DB::table('VaiTro')
                ->where('TenVaiTro', self::ROLE_LABELS[$slug] ?? $slug)
                ->first();

            if (! $role) {
                return;
            }

            $exists = DB::table('TaiKhoan_VaiTro')
                ->where('TaiKhoanID', $user->getKey())
                ->where('VaiTroID', $role->VaiTroID)
                ->exists();

            if ($exists) {
                return;
            }

            DB::table('TaiKhoan_VaiTro')->insert([
                'TaiKhoanID' => $user->getKey(),
                'VaiTroID' => $role->VaiTroID,
            ]);
        });
    }

    /**
     * Tài khoản Chủ cửa hàng (toàn quyền).
     */
    public function owner(): static
    {
        return $this->attachRole('owner');
    }

    /**
     * Tên cũ của `owner()`, giữ lại cho các test cũ.
     */
    public function admin(): static
    {
        return $this->owner();
    }

    public function manager(): static
    {
        return $this->attachRole('manager');
    }

    public function staff(): static
    {
        return $this->attachRole('staff');
    }

    /**
     * Tên cũ của `staff()`, giữ lại cho các test cũ.
     */
    public function employee(): static
    {
        return $this->staff();
    }

    public function customer(): static
    {
        return $this->attachRole('customer');
    }

    /**
     * Tài khoản đã bị khoá.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'TrangThai' => 'Đã khóa',
        ]);
    }
}
