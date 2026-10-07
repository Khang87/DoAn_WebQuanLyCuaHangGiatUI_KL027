<?php

namespace App\Services;

use App\Models\Quyen;
use App\Models\VaiTro;
use App\Support\PermissionCache;
use Illuminate\Support\Facades\DB;

/** Data-only provisioning; never changes the existing Supabase schema. */
class DefaultNotificationPermission
{
    public const CODE = 'NOTIFICATION_VIEW';

    public function permission(): Quyen
    {
        $permission = Quyen::query()->firstOrCreate(
            ['MaQuyen' => self::CODE],
            ['TenQuyen' => 'Xem thông báo cá nhân', 'MoTa' => 'Quyền mặc định của mọi nhóm.', 'TrangThai' => 'Hoạt động'],
        );
        if ($permission->TrangThai !== 'Hoạt động') {
            $permission->update(['TrangThai' => 'Hoạt động']);
        }

        return $permission;
    }

    public function assign(VaiTro $role): void
    {
        $role->quyens()->syncWithoutDetaching([$this->permission()->getKey()]);
        PermissionCache::forgetAll();
    }

    public function backfill(): int
    {
        $count = DB::transaction(function (): int {
            $permission = $this->permission();
            $count = 0;
            VaiTro::query()->orderBy('VaiTroID')->chunkById(200, function ($roles) use ($permission, &$count): void {
                foreach ($roles as $role) {
                    $role->quyens()->syncWithoutDetaching([$permission->getKey()]);
                    $count++;
                }
            }, 'VaiTroID');

            return $count;
        });
        PermissionCache::forgetAll();

        return $count;
    }
}
