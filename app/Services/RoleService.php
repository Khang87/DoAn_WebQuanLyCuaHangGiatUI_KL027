<?php

namespace App\Services;

use App\Models\Quyen;
use App\Models\VaiTro;
use App\Support\PermissionRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            ->with('quyens')
            ->orderByRaw("CASE TenVaiTro WHEN 'Chủ cửa hàng' THEN 0 WHEN 'Quản lý' THEN 1 WHEN 'Nhân viên' THEN 2 ELSE 3 END")
            ->get();
    }

    /**
     * Ma trận quyền: group => [code => ['name' => ..., 'owner_only' => bool]].
     * Mỗi permission kèm danh sách tên của các vai trò đang được cấp quyền.
     *
     * @return array<string, array<string, array{name: string, owner_only: bool, roles: list<string>}>>
     */
    public function matrix(): array
    {
        $roles = $this->getAllWithPermissions();
        $granted = $roles->mapWithKeys(fn (VaiTro $role) => [
            $role->TenVaiTro => $role->quyens->pluck('MaQuyen')->all(),
        ]);

        $rows = [];

        foreach (PermissionRegistry::groups() as $group => $items) {
            foreach ($items as $code => $name) {
                $rows[$group][$code] = [
                    'name' => $name,
                    'owner_only' => in_array($code, VaiTro::OWNER_ONLY_PERMISSIONS, true),
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
     * Quyền đặc biệt (VaiTro::OWNER_ONLY_PERMISSIONS) bị ép buộc về đúng Chủ cửa hàng,
     * bất kể payload gửi lên, để không thể vô tình cấp quyền tài chính cho
     * Nhân viên / Quản lý.
     *
     * @param  array<string, array<int, string>>  $matrix  role_name => [permission_code, ...]
     * @return array{granted: int, rejected: int}
     */
    public function syncPermissions(array $matrix): array
    {
        $roles = VaiTro::query()->get()->keyBy('TenVaiTro');
        $permissions = Quyen::query()->get()->keyBy('MaQuyen');

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

                    $ids[] = $permission->QuyenID;
                    $granted++;
                }

                $role->quyens()->sync($ids);
            }
        });

        return ['granted' => $granted, 'rejected' => $rejected];
    }
}
