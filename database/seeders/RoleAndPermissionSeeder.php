<?php

namespace Database\Seeders;

use App\Models\Quyen;
use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Nạp vai trò và quyền hạn vào các bảng `VaiTro`, `Quyen`, `VaiTro_Quyen`.
 *
 * Mã quyền trong bảng `Quyen` viết dạng SCREAMING_SNAKE (`ORDER_VIEW`) khác với
 * mã trong `PermissionRegistry` (`orders.view`), nên bảng tra dưới đây là cầu
 * nối giữa hai hệ thống. Nếu thêm quyền mới vào `PermissionRegistry` thì cần
 * bổ sung mã tương ứng vào `PERMISSION_CODES`.
 */
class RoleAndPermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Danh sách vai trò chuẩn: tên trong bảng `VaiTro` => mô tả.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        'Chủ cửa hàng' => 'Toàn quyền, bao gồm sửa/xoá đơn đã thanh toán và hoàn tiền.',
        'Quản lý' => 'Quản lý vận hành, không sửa/xoá đơn đã quyết toán hay hoàn tiền.',
        'Nhân viên' => 'Xử lý đơn, giao nhận và khách hàng. Không chạm tới tài chính đã thanh toán.',
        'Khách hàng' => 'Tài khoản khách hàng, không truy cập khu vực quản lý.',
    ];

    /**
     * Toàn bộ mã quyền trong hệ thống, dạng SCREAMING_SNAKE như bảng `Quyen`.
     *
     * @var array<string, string> mã quyền => tên hiển thị
     */
    public const PERMISSION_CODES = [
        'DASHBOARD_VIEW' => 'Xem Dashboard',
        'ORDER_VIEW' => 'Xem đơn hàng',
        'ORDER_CREATE' => 'Tạo đơn hàng',
        'ORDER_UPDATE' => 'Cập nhật đơn hàng',
        'ORDER_DELETE' => 'Xóa đơn hàng',
        'SERVICE_MANAGE' => 'Quản lý dịch vụ',
        'PRICE_MANAGE' => 'Quản lý bảng giá',
        'PROMOTION_MANAGE' => 'Quản lý khuyến mãi',
        'CUSTOMER_VIEW' => 'Xem khách hàng',
        'CUSTOMER_MANAGE' => 'Quản lý khách hàng',
        'DELIVERY_MANAGE' => 'Quản lý giao nhận',
        'REPORT_VIEW' => 'Xem báo cáo',
        'INVOICE_VIEW' => 'Xem hóa đơn',
        'INVOICE_UPDATE' => 'Cập nhật hóa đơn',
        'PAYMENT_CREATE' => 'Tạo thanh toán',
        'ACCOUNT_MANAGE' => 'Quản lý tài khoản',
        'ROLE_MANAGE' => 'Quản lý vai trò',
        'SYSTEM_FULL_ACCESS' => 'Toàn quyền hệ thống',
    ];

    /**
     * Ma trận quyền mặc định: tên vai trò => danh sách mã quyền được cấp.
     *
     * Chủ cửa hàng luôn có toàn quyền nên không cần liệt kê.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'Quản lý' => [
            'DASHBOARD_VIEW', 'ORDER_VIEW', 'ORDER_CREATE', 'ORDER_UPDATE',
            'INVOICE_VIEW', 'PAYMENT_CREATE', 'SERVICE_MANAGE', 'PRICE_MANAGE',
            'PROMOTION_MANAGE', 'CUSTOMER_VIEW', 'CUSTOMER_MANAGE',
            'DELIVERY_MANAGE', 'REPORT_VIEW',
        ],
        'Nhân viên' => [
            'DASHBOARD_VIEW', 'ORDER_VIEW', 'ORDER_CREATE', 'ORDER_UPDATE',
            'INVOICE_VIEW', 'PAYMENT_CREATE', 'CUSTOMER_VIEW', 'DELIVERY_MANAGE',
        ],
        'Khách hàng' => [
            'ORDER_VIEW', 'ORDER_CREATE',
        ],
    ];

    /**
     * Mã quyền chỉ Chủ cửa hàng được giữ, các vai trò khác không bao giờ được cấp.
     *
     * @var list<string>
     */
    public const OWNER_ONLY_PERMISSIONS = [
        'ORDER_DELETE',
        'INVOICE_UPDATE',
        'ACCOUNT_MANAGE',
        'ROLE_MANAGE',
        'SYSTEM_FULL_ACCESS',
    ];

    /**
     * Ba tài khoản thử nghiệm chuẩn, đúng thứ tự phân cấp tài khoản.
     *
     * @var list<array{username: string, email: string, phone: string, role: string}>
     */
    public const STANDARD_ACCOUNTS = [
        ['username' => 'owner', 'email' => 'admin@gmail.com', 'phone' => '0900000001', 'role' => 'Chủ cửa hàng'],
        ['username' => 'quanly', 'email' => 'manager@gmail.com', 'phone' => '0900000002', 'role' => 'Quản lý'],
        ['username' => 'nhanvien1', 'email' => 'staff@gmail.com', 'phone' => '0900000003', 'role' => 'Nhân viên'],
    ];

    public const STANDARD_PASSWORD = '123456';

    public function run(): void
    {
        $roles = $this->seedRoles();
        $permissions = $this->seedPermissions();

        $this->syncRolePermissions($roles, $permissions);
        $this->seedStandardAccounts($roles);
    }

    /**
     * @return array<string, VaiTro> key = tên vai trò
     */
    private function seedRoles(): array
    {
        $roles = [];

        foreach (self::ROLES as $name => $description) {
            $roles[$name] = VaiTro::updateOrCreate(
                ['TenVaiTro' => $name],
                ['MoTa' => $description, 'TrangThai' => 'Hoạt động'],
            );
        }

        return $roles;
    }

    /**
     * @return array<string, Quyen> key = mã quyền
     */
    private function seedPermissions(): array
    {
        $permissions = [];

        foreach (self::PERMISSION_CODES as $code => $name) {
            $permissions[$code] = Quyen::updateOrCreate(
                ['MaQuyen' => $code],
                ['TenQuyen' => $name, 'TrangThai' => 'Hoạt động'],
            );
        }

        return $permissions;
    }

    /**
     * Đồng bộ ma trận quyền cho từng vai trò.
     *
     * @param  array<string, VaiTro>  $roles
     * @param  array<string, Quyen>  $permissions
     */
    private function syncRolePermissions(array $roles, array $permissions): void
    {
        foreach ($roles as $name => $role) {
            // Chủ cửa hàng nắm toàn bộ quyền.
            $codes = $name === 'Chủ cửa hàng'
                ? array_keys($permissions)
                : (self::ROLE_PERMISSIONS[$name] ?? []);

            $ids = [];

            foreach ($codes as $code) {
                // Chặn vô tình cấp quyền dành riêng cho Chủ cửa hàng.
                if ($name !== 'Chủ cửa hàng' && in_array($code, self::OWNER_ONLY_PERMISSIONS, true)) {
                    continue;
                }

                if (isset($permissions[$code])) {
                    $ids[] = $permissions[$code]->getKey();
                }
            }

            $this->syncPivot($role, $ids);
        }
    }

    /**
     * Ghi quan hệ vào `VaiTro_Quyen` (bảng nối không có khoá chính).
     *
     * @param  list<int>  $permissionIds
     */
    private function syncPivot(VaiTro $role, array $permissionIds): void
    {
        DB::table('VaiTro_Quyen')
            ->where('VaiTroID', $role->getKey())
            ->whereNotIn('QuyenID', $permissionIds)
            ->delete();

        $existing = DB::table('VaiTro_Quyen')
            ->where('VaiTroID', $role->getKey())
            ->pluck('QuyenID')
            ->all();

        foreach (array_diff($permissionIds, $existing) as $permissionId) {
            DB::table('VaiTro_Quyen')->insert([
                'VaiTroID' => $role->getKey(),
                'QuyenID' => $permissionId,
            ]);
        }
    }

    /**
     * Bảo đảm đủ ba tài khoản thử nghiệm chuẩn, không phụ thuộc UserSeeder.
     *
     * @param  array<string, VaiTro>  $roles
     */
    private function seedStandardAccounts(array $roles): void
    {
        foreach (self::STANDARD_ACCOUNTS as $account) {
            $role = $roles[$account['role']] ?? null;

            if (! $role) {
                continue;
            }

            $user = User::updateOrCreate(
                ['Email' => $account['email']],
                [
                    'TenDangNhap' => $account['username'],
                    'MatKhau' => Hash::make(self::STANDARD_PASSWORD),
                    'SoDienThoai' => $account['phone'],
                    'TrangThai' => 'Hoạt động',
                    'NgayTao' => now(),
                ],
            );

            $exists = DB::table('TaiKhoan_VaiTro')
                ->where('TaiKhoanID', $user->getKey())
                ->where('VaiTroID', $role->getKey())
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('TaiKhoan_VaiTro')->insert([
                'TaiKhoanID' => $user->getKey(),
                'VaiTroID' => $role->getKey(),
            ]);
        }
    }
}
