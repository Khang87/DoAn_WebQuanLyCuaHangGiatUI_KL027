<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * Vai trò kèm danh sách mã quyền đang được cấp.
     *
     * @return Collection<int, Role>
     */
    public function getAllWithPermissions(): Collection
    {
        return Role::query()
            ->with('permissions')
            ->orderByRaw("CASE slug WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 WHEN 'staff' THEN 2 ELSE 3 END")
            ->get();
    }

    /**
     * Ma trận quyền: group => [code => ['name' => ..., 'owner_only' => bool]].
     * Mỗi permission kèm danh sách slug của các vai trò đang được cấp quyền.
     *
     * @return array<string, array<string, array{name: string, owner_only: bool, roles: list<string>}>>
     */
    public function matrix(): array
    {
        $roles = $this->getAllWithPermissions();
        $granted = $roles->mapWithKeys(fn (Role $role) => [
            $role->slug => $role->permissions->pluck('code')->all(),
        ]);

        $rows = [];

        foreach (PermissionRegistry::groups() as $group => $items) {
            foreach ($items as $code => $name) {
                $rows[$group][$code] = [
                    'name' => $name,
                    'owner_only' => in_array($code, Role::OWNER_ONLY_PERMISSIONS, true),
                    'roles' => $granted
                        ->filter(fn (array $codes) => in_array($code, $codes, true))
                        ->keys()
                        ->values()
                        ->all(),
                ];
            }
        }

        return $rows;
    }

    /**
     * Lưu ma trận quyền.
     *
     * Quyền đặc biệt (Role::OWNER_ONLY_PERMISSIONS) bị ép buộc về đúng Chủ cửa hàng,
     * bất kể payload gửi lên, để không thể vô tình cấp quyền tài chính cho
     * Nhân viên / Quản lý.
     *
     * @param  array<string, array<int, string>>  $matrix  role_slug => [permission_code, ...]
     * @return array{granted: int, rejected: int}
     */
    public function syncPermissions(array $matrix): array
    {
        $roles = Role::query()->get()->keyBy('slug');
        $permissions = Permission::query()->get()->keyBy('code');

        $granted = 0;
        $rejected = 0;

        DB::transaction(function () use ($matrix, $roles, $permissions, &$granted, &$rejected) {
            foreach ($matrix as $slug => $codes) {
                $role = $roles->get($slug);

                if (! $role) {
                    continue;
                }

                $ids = [];

                foreach (array_unique((array) $codes) as $code) {
                    $permission = $permissions->get($code);

                    if (! $permission) {
                        continue;
                    }

                    if (! $role->mayHold($code)) {
                        $rejected++;

                        continue;
                    }

                    $ids[] = $permission->id;
                    $granted++;
                }

                $role->permissions()->sync($ids);
            }
        });

        return ['granted' => $granted, 'rejected' => $rejected];
    }
}
