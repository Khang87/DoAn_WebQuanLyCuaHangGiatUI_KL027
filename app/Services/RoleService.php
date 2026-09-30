<?php

namespace App\Services;

use App\Models\Quyen;
use App\Models\VaiTro;
use App\Support\PermissionCache;
use App\Support\QuyenMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đọc và ghi ma trận quyền trên các bảng `VaiTro`, `Quyen`, `VaiTro_Quyen`.
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
            ->with('quyens')
            ->orderByRaw(
                'CASE "TenVaiTro" WHEN \'Chủ cửa hàng\' THEN 0 WHEN \'Quản lý\' THEN 1'
                .' WHEN \'Nhân viên\' THEN 2 ELSE 3 END'
            )
            ->get();
    }

    /**
     * @return Collection<int, Quyen>
     */
    public function getAllPermissions(): Collection
    {
        return Quyen::query()
            ->orderBy('MaQuyen')
            ->get(['QuyenID', 'MaQuyen', 'TenQuyen', 'MoTa', 'TrangThai']);
    }

    /**
     * Lưu ma trận quyền.
     *
     * Quyền chỉ dành cho Chủ cửa hàng bị từ chối với mọi vai trò khác. Các quyền
     * đang ngừng hoạt động được giữ nguyên nhưng không thể cấp mới từ giao diện.
     *
     * @param  array<string, array<int, int>>  $matrix  slug vai trò => [QuyenID, ...]
     * @return array{granted: int, rejected: int}
     */
    public function syncPermissions(array $matrix): array
    {
        $permissions = Quyen::query()->get(['QuyenID', 'MaQuyen', 'TrangThai']);
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

        // Ma trận gửi lên dùng slug vai trò (`quan-ly`) nên keyBy theo slug.
        $roles = $this->getAllWithPermissions()->keyBy(fn (VaiTro $vaiTro) => $vaiTro->slug);

        $granted = 0;
        $rejected = 0;

        DB::transaction(function () use ($matrix, $roles, $activeIds, $inactiveIds, $ownerOnlyIds, &$granted, &$rejected) {
            foreach ($matrix as $slug => $permissionIds) {
                $vaiTro = $roles->get($slug);

                // Vai trò không tồn tại, hoặc là Chủ cửa hàng (luôn có toàn quyền).
                if (! $vaiTro || $vaiTro->isOwner()) {
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
}
