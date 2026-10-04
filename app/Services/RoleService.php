<?php

namespace App\Services;

use App\Models\Quyen;
use App\Models\VaiTro;
use App\Support\PermissionCache;
use App\Support\PermissionRegistry;
use App\Support\QuyenMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Đọc và ghi quyền trên các bảng `VaiTro`, `Quyen`, `VaiTro_Quyen`.
 *
 * Mọi thao tác ở đây dùng đúng tên bảng/cột tiếng Việt của Supabase
 * (VaiTro, Quyen, VaiTro_Quyen, TenVaiTro, MaQuyen, VaiTroID, QuyenID).
 */
class RoleService
{
    /**
     * Vai trò kèm danh sách mã quyền đang được cấp.
     *
     * @return Collection<int, VaiTro>
     */
    public function getAllWithPermissions(): Collection
    {
        return VaiTro::query()
            ->with([
                'quyens' => fn ($query) => $query
                    ->where('Quyen.TrangThai', 'Hoạt động')
                    ->whereIn('Quyen.MaQuyen', $this->configuredMaQuyens())
                    ->orderBy('TenQuyen'),
            ])
            ->withCount([
                'quyens as permission_count' => fn ($query) => $query
                    ->where('Quyen.TrangThai', 'Hoạt động')
                    ->whereIn('Quyen.MaQuyen', $this->configuredMaQuyens()),
                'taiKhoans as member_count',
            ])
            ->orderByRaw(
                'CASE "TenVaiTro" WHEN \'Chủ cửa hàng\' THEN 0 WHEN \'Quản lý\' THEN 1'
                .' WHEN \'Nhân viên\' THEN 2 ELSE 3 END'
            )
            ->get();
    }

    /**
     * @return array{
     *     roles: Collection<int, VaiTro>,
     *     metrics: array{roles: int, permissions: int, assignedAccounts: int}
     * }
     */
    public function getRoleManagementData(): array
    {
        return [
            'roles' => $this->getAllWithPermissions(),
            'metrics' => [
                'roles' => (int) VaiTro::query()->count(),
                'permissions' => (int) Quyen::query()
                    ->where('TrangThai', 'Hoạt động')
                    ->whereIn('MaQuyen', $this->configuredMaQuyens())
                    ->count(),
                'assignedAccounts' => (int) DB::table('TaiKhoan_VaiTro')
                    ->distinct()
                    ->count('TaiKhoanID'),
            ],
        ];
    }

    /**
     * @return Collection<int, Quyen>
     */
    public function getAllPermissions(): Collection
    {
        return Quyen::query()
            ->whereIn('MaQuyen', $this->configuredMaQuyens())
            ->orderBy('MaQuyen')
            ->get(['QuyenID', 'MaQuyen', 'TenQuyen', 'MoTa', 'TrangThai']);
    }

    public function isManagedPermission(Quyen $permission): bool
    {
        return in_array((string) $permission->MaQuyen, $this->configuredMaQuyens(), true);
    }

    /**
     * @return Collection<string, Collection<int, Quyen>>
     */
    public function getActivePermissionGroups(): Collection
    {
        return $this->getAllPermissions()
            ->where('TrangThai', 'Hoạt động')
            ->groupBy(fn (Quyen $permission): string => $this->permissionModuleLabel((string) $permission->MaQuyen));
    }

    /**
     * Permission catalog data grouped for the task-list management screen.
     *
     * @return array{
     *     roles: Collection<int, VaiTro>,
     *     permissionGroups: Collection<string, Collection<int, Quyen>>,
     *     metrics: array{roles: int, permissions: int, assignedAccounts: int}
     * }
     */
    public function getPermissionManagementData(): array
    {
        $roles = VaiTro::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('TenVaiTro')
            ->get();
        $permissions = Quyen::query()
            ->whereIn('MaQuyen', $this->configuredMaQuyens())
            ->with(['vaiTros' => fn ($query) => $query->orderBy('TenVaiTro')])
            ->orderBy('MaQuyen')
            ->get();

        return [
            'roles' => $roles,
            'permissionGroups' => $permissions->groupBy(
                fn (Quyen $permission): string => $this->permissionModuleLabel((string) $permission->MaQuyen)
            ),
            'metrics' => [
                'roles' => (int) VaiTro::query()->count(),
                'permissions' => (int) Quyen::query()
                    ->where('TrangThai', 'Hoạt động')
                    ->whereIn('MaQuyen', $this->configuredMaQuyens())
                    ->count(),
                'assignedAccounts' => (int) DB::table('TaiKhoan_VaiTro')
                    ->distinct()
                    ->count('TaiKhoanID'),
            ],
        ];
    }

    public function createRole(array $attributes): VaiTro
    {
        return VaiTro::query()->create([
            'TenVaiTro' => trim($attributes['TenVaiTro']),
            'MoTa' => $attributes['MoTa'] ?? null,
            'TrangThai' => 'Hoạt động',
        ]);
    }

    public function updateRole(VaiTro $role, array $attributes): VaiTro
    {
        if ($role->isOwner() && trim($attributes['TenVaiTro']) !== VaiTro::OWNER) {
            throw ValidationException::withMessages([
                'TenVaiTro' => 'Không thể đổi tên vai trò Chủ cửa hàng.',
            ]);
        }

        $role->update([
            'TenVaiTro' => trim($attributes['TenVaiTro']),
            'MoTa' => $attributes['MoTa'] ?? null,
            'TrangThai' => $role->isOwner()
                ? 'Hoạt động'
                : ($attributes['TrangThai'] ?? $role->TrangThai),
        ]);

        return $role->refresh();
    }

    /**
     * @return 'deleted'|'deactivated'
     */
    public function deleteRole(VaiTro $role): string
    {
        if ($role->isOwner()) {
            throw ValidationException::withMessages([
                'role' => 'Không thể xóa hoặc vô hiệu hóa vai trò Chủ cửa hàng.',
            ]);
        }

        return DB::transaction(function () use ($role): string {
            $lockedRole = VaiTro::query()->lockForUpdate()->findOrFail($role->getKey());

            if ($lockedRole->taiKhoans()->exists()) {
                $lockedRole->update(['TrangThai' => 'Ngừng hoạt động']);

                return 'deactivated';
            }

            $lockedRole->quyens()->detach();
            $lockedRole->delete();

            return 'deleted';
        });
    }

    /**
     * Lưu ma trận quyền.
     *
     * Quyền chỉ dành cho Chủ cửa hàng bị từ chối với mọi vai trò khác. Các quyền
     * đang ngừng hoạt động được giữ nguyên nhưng không thể cấp mới từ giao diện.
     *
     * @param  array<int, array<int, int>>  $matrix  VaiTroID => [QuyenID, ...]
     * @return array{granted: int, rejected: int}
     */
    public function syncPermissions(array $matrix): array
    {
        $permissions = Quyen::query()
            ->whereIn('MaQuyen', $this->configuredMaQuyens())
            ->get(['QuyenID', 'MaQuyen', 'TrangThai']);
        $activeIds = $permissions
            ->where('TrangThai', 'Hoạt động')
            ->pluck('QuyenID')
            ->map(fn ($id) => (int) $id)
            ->all();
        $inactiveIds = $permissions
            ->filter(
                fn (Quyen $quyen) => $quyen->TrangThai !== 'Hoạt động'
                    && ! QuyenMapper::isOwnerOnlyMaQuyen((string) $quyen->MaQuyen)
            )
            ->pluck('QuyenID')
            ->map(fn ($id) => (int) $id)
            ->all();
        $ownerOnlyIds = $permissions
            ->filter(fn (Quyen $quyen) => QuyenMapper::isOwnerOnlyMaQuyen((string) $quyen->MaQuyen))
            ->pluck('QuyenID')
            ->map(fn ($id) => (int) $id)
            ->all();

        $roles = $this->getAllWithPermissions()->keyBy(fn (VaiTro $vaiTro) => (int) $vaiTro->getKey());

        $granted = 0;
        $rejected = 0;

        DB::transaction(function () use ($matrix, $roles, $activeIds, $inactiveIds, $ownerOnlyIds, &$granted, &$rejected) {
            foreach ($matrix as $roleId => $permissionIds) {
                $vaiTro = $roles->get((int) $roleId);

                // Vai trò không tồn tại, hoặc là Chủ cửa hàng (luôn có toàn quyền).
                if (! $vaiTro || $vaiTro->isOwner() || $vaiTro->TrangThai !== 'Hoạt động') {
                    continue;
                }

                $ids = [];

                foreach (array_unique(array_map('intval', (array) $permissionIds)) as $permissionId) {
                    if (! in_array($permissionId, $activeIds, true)) {
                        continue;
                    }

                    if (in_array($permissionId, $ownerOnlyIds, true)) {
                        $rejected++;

                        continue;
                    }

                    $ids[$permissionId] = true;
                }

                $preservedInactiveIds = DB::table('VaiTro_Quyen')
                    ->where('VaiTroID', $vaiTro->getKey())
                    ->whereIn('QuyenID', $inactiveIds)
                    ->pluck('QuyenID')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $vaiTro->quyens()->sync(array_values(array_unique([
                    ...array_map('intval', array_keys($ids)),
                    ...$preservedInactiveIds,
                ])));

                $granted += count($ids);
            }
        });

        // Quyền được cache trong request nên request tiếp theo sẽ đọc trạng thái mới.
        PermissionCache::forgetAll();

        return ['granted' => $granted, 'rejected' => $rejected];
    }

    /**
     * Replace the role assignments for one permission without changing schema.
     *
     * @param  list<int>  $roleIds
     */
    public function syncPermissionRoles(Quyen $permission, array $roleIds): void
    {
        if (! $this->isManagedPermission($permission)) {
            throw ValidationException::withMessages([
                'permission' => 'Chỉ có thể gán quyền đã được cấu hình trong danh mục ứng dụng.',
            ]);
        }

        $roles = VaiTro::query()
            ->whereIn('VaiTroID', array_values(array_unique(array_map('intval', $roleIds))))
            ->where('TrangThai', 'Hoạt động')
            ->get();

        if ($roles->count() !== count(array_unique(array_map('intval', $roleIds)))) {
            throw ValidationException::withMessages([
                'vai_tro_ids' => 'Một hoặc nhiều nhóm quyền không còn hoạt động.',
            ]);
        }

        if (QuyenMapper::isOwnerOnlyMaQuyen((string) $permission->MaQuyen) && $roles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'vai_tro_ids' => 'Quyền này chỉ dành riêng cho Chủ cửa hàng.',
            ]);
        }

        $editableRoleIds = VaiTro::query()
            ->where('TrangThai', 'Hoạt động')
            ->where('TenVaiTro', '!=', VaiTro::OWNER)
            ->pluck('VaiTroID')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $selectedRoleIds = $roles
            ->reject(fn (VaiTro $role): bool => $role->isOwner())
            ->modelKeys();

        DB::transaction(function () use ($permission, $editableRoleIds, $selectedRoleIds): void {
            Quyen::query()->lockForUpdate()->findOrFail($permission->getKey());

            $detachRoleIds = array_values(array_diff($editableRoleIds, $selectedRoleIds));

            if ($detachRoleIds !== []) {
                DB::table('VaiTro_Quyen')
                    ->where('QuyenID', $permission->getKey())
                    ->whereIn('VaiTroID', $detachRoleIds)
                    ->delete();
            }

            foreach ($selectedRoleIds as $roleId) {
                DB::table('VaiTro_Quyen')->insertOrIgnore([
                    'VaiTroID' => $roleId,
                    'QuyenID' => $permission->getKey(),
                ]);
            }
        });

        PermissionCache::forgetAll();
    }

    /**
     * Replace the active permission assignments for one role and retain any
     * existing assignments to inactive permissions.
     *
     * @param  list<int>  $permissionIds
     */
    public function syncRolePermissions(VaiTro $role, array $permissionIds): void
    {
        if ($role->isOwner()) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Vai trò Chủ cửa hàng luôn có toàn quyền và không thể chỉnh sửa.',
            ]);
        }

        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        $permissions = Quyen::query()
            ->whereIn('QuyenID', $permissionIds)
            ->whereIn('MaQuyen', $this->configuredMaQuyens())
            ->where('TrangThai', 'Hoạt động')
            ->get(['QuyenID', 'MaQuyen']);

        if ($permissions->count() !== count($permissionIds)) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Một hoặc nhiều quyền không còn hoạt động hoặc chưa được cấu hình trong ứng dụng.',
            ]);
        }

        if ($permissions->contains(
            fn (Quyen $permission): bool => QuyenMapper::isOwnerOnlyMaQuyen((string) $permission->MaQuyen)
        )) {
            throw ValidationException::withMessages([
                'permission_ids' => 'Một hoặc nhiều quyền chỉ dành riêng cho Chủ cửa hàng.',
            ]);
        }

        DB::transaction(function () use ($role, $permissionIds): void {
            $lockedRole = VaiTro::query()->lockForUpdate()->findOrFail($role->getKey());

            if ($lockedRole->isOwner()) {
                throw ValidationException::withMessages([
                    'permission_ids' => 'Vai trò Chủ cửa hàng luôn có toàn quyền và không thể chỉnh sửa.',
                ]);
            }

            if ($lockedRole->TrangThai !== 'Hoạt động') {
                throw ValidationException::withMessages([
                    'permission_ids' => 'Không thể chỉnh sửa quyền của nhóm đã ngừng hoạt động.',
                ]);
            }

            $inactivePermissionIds = DB::table('VaiTro_Quyen')
                ->join('Quyen', 'Quyen.QuyenID', '=', 'VaiTro_Quyen.QuyenID')
                ->where('VaiTro_Quyen.VaiTroID', $lockedRole->getKey())
                ->where('Quyen.TrangThai', '!=', 'Hoạt động')
                ->pluck('VaiTro_Quyen.QuyenID')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $lockedRole->quyens()->sync(array_values(array_unique([
                ...$permissionIds,
                ...$inactivePermissionIds,
            ])));
        });

        PermissionCache::forgetAll();
    }

    private function permissionModuleLabel(string $permissionCode): string
    {
        if (str_starts_with(strtoupper($permissionCode), 'SYSTEM_LOGS_')) {
            return 'Nhật ký hệ thống';
        }

        $module = explode('_', strtoupper($permissionCode), 2)[0];

        return [
            'ACCOUNTING' => 'Kế toán',
            'ACCOUNT' => 'Tài khoản',
            'BOOKING' => 'Đặt lịch',
            'COUPON' => 'Mã giảm giá',
            'CUSTOMER' => 'Khách hàng',
            'DASHBOARD' => 'Tổng quan',
            'DELIVERY' => 'Giao nhận',
            'GARMENT' => 'Đồ giặt',
            'INVOICE' => 'Hóa đơn',
            'MESSAGES' => 'Tin nhắn',
            'NOTIFICATION' => 'Thông báo',
            'ORDER' => 'Đơn hàng',
            'PAYMENT' => 'Thanh toán',
            'PRICE' => 'Bảng giá',
            'PROMOTION' => 'Khuyến mãi',
            'REPORT' => 'Báo cáo',
            'REVIEW' => 'Đánh giá',
            'ROLE' => 'Phân quyền',
            'SERVICE' => 'Dịch vụ',
            'SYSTEM' => 'Hệ thống',
        ][$module] ?? Str::headline(strtolower($module));
    }

    /**
     * Database codes mapped from the developer-owned permission registry.
     *
     * @return list<string>
     */
    private function configuredMaQuyens(): array
    {
        return collect(PermissionRegistry::codes())
            ->map(fn (string $code): ?string => QuyenMapper::resolveMaQuyen($code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
