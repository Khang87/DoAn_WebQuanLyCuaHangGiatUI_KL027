<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Ma trận quyền mặc định: slug vai trò => danh sách module được toàn quyền.
     * Mọi quyền trong Role::OWNER_ONLY_PERMISSIONS đều bị bỏ qua ở các vai trò
     * không phải Chủ cửa hàng.
     */
    public const ROLE_MODULES = [
        Role::OWNER_SLUG => ['*'],
        'manager' => [
            'Đơn hàng', 'Hóa đơn', 'Thanh toán', 'Dịch vụ', 'Khách hàng',
            'Giao nhận', 'Đồ giặt & Giá', 'Khuyến mãi', 'Đánh giá & Thông báo',
            'Báo cáo', 'Tài khoản & Phân quyền',
        ],
        'staff' => [
            'Đơn hàng', 'Dịch vụ', 'Khách hàng', 'Giao nhận',
            'Đồ giặt & Giá', 'Đánh giá & Thông báo',
        ],
        'customer' => [],
    ];

    public const ROLES = [
        Role::OWNER_SLUG => [
            'name' => 'Chủ cửa hàng',
            'description' => 'Toàn quyền, bao gồm sửa/xóa đơn đã thanh toán và hoàn tiền.',
        ],
        'manager' => [
            'name' => 'Quản lý',
            'description' => 'Quản lý vận hành, không được sửa/xóa đơn đã quyết toán hay hoàn tiền.',
        ],
        'staff' => [
            'name' => 'Nhân viên',
            'description' => 'Xử lý đơn, giao nhận và khách hàng. Không chạm tới tài chính đã thanh toán.',
        ],
        'customer' => [
            'name' => 'Khách hàng',
            'description' => 'Tài khoản khách hàng, không truy cập khu vực quản lý.',
        ],
    ];

    /**
     * Ba tài khoản thử nghiệm chuẩn, đúng phân cấp tài khoản:
     * Chủ cửa hàng (admin) > Quản lý (manager) > Nhân viên (staff).
     */
    public const STANDARD_ACCOUNTS = [
        [
            'name' => 'Chủ cửa hàng',
            'email' => 'admin@gmail.com',
            'phone' => '0900000001',
            'role' => 'admin',
        ],
        [
            'name' => 'Quản lý cửa hàng',
            'email' => 'manager@gmail.com',
            'phone' => '0900000002',
            'role' => 'manager',
        ],
        [
            'name' => 'Nhân viên',
            'email' => 'staff@gmail.com',
            'phone' => '0900000003',
            'role' => 'staff',
        ],
    ];

    public const STANDARD_PASSWORD = '123456';

    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles();
        $this->syncRolePermissions($roles, $permissions);
        $this->backfillUserRoles($roles);
        $this->seedStandardAccounts($roles);
    }

    /**
     * @return array<string, Permission> key = group, value = mảng code => Permission
     */
    private function seedPermissions(): array
    {
        $grouped = [];

        foreach (PermissionRegistry::groups() as $group => $items) {
            $grouped[$group] = [];

            foreach ($items as $code => $name) {
                $grouped[$group][$code] = Permission::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'group' => $group],
                );
            }
        }

        return $grouped;
    }

    /**
     * @return array<string, Role> key = slug
     */
    private function seedRoles(): array
    {
        $roles = [];

        foreach (self::ROLES as $slug => $meta) {
            $roles[$slug] = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $meta['name'], 'description' => $meta['description']],
            );
        }

        return $roles;
    }

    /**
     * @param  array<string, Role>  $roles
     * @param  array<string, array<string, Permission>>  $permissions
     */
    private function syncRolePermissions(array $roles, array $permissions): void
    {
        foreach ($roles as $slug => $role) {
            $modules = self::ROLE_MODULES[$slug] ?? [];
            $codes = [];

            foreach ($permissions as $group => $items) {
                if (! in_array('*', $modules, true) && ! in_array($group, $modules, true)) {
                    continue;
                }

                foreach ($items as $code => $permission) {
                    if ($role->mayHold($code)) {
                        $codes[] = $permission->id;
                    }
                }
            }

            $role->permissions()->sync($codes);
        }
    }

    /**
     * Gán role_id cho tài khoản đã tồn tại dựa trên cột `role` cũ.
     * Việc quy đổi dùng Role::normalizeSlug() nên 'admin' -> owner và
     * 'employee' -> staff, tránh rơi nhầm vai trò (vd về customer và mất quyền).
     *
     * @param  array<string, Role>  $roles
     */
    private function backfillUserRoles(array $roles): void
    {
        User::withTrashed()->get()->each(function (User $user) use ($roles) {
            $slug = Role::normalizeSlug($user->role);

            $role = $slug !== null ? ($roles[$slug] ?? null) : null;

            if ($role && $user->role_id !== $role->id) {
                // Cập nhật trực tiếp qua DB để không kích hoạt timestamp.
                DB::table('users')->where('id', $user->id)->update(['role_id' => $role->id]);
            }
        });
    }

    /**
     * Bảo đảm đủ ba tài khoản thử nghiệm chuẩn, không phụ thuộc UserSeeder.
     * Dùng updateOrCreate theo email nên chạy lại nhiều lần vẫn an toàn.
     *
     * @param  array<string, Role>  $roles
     */
    private function seedStandardAccounts(array $roles): void
    {
        foreach (self::STANDARD_ACCOUNTS as $account) {
            /** @var Role|null $role */
            $role = $roles[Role::normalizeSlug($account['role'])] ?? null;

            if (! $role) {
                continue;
            }

            User::withTrashed()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'role' => $account['role'],
                    'role_id' => $role->id,
                    'password' => Hash::make(self::STANDARD_PASSWORD),
                ],
            );
        }
    }
}
