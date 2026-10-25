<?php

namespace App\Support;

use App\Models\Quyen;
use App\Models\VaiTro;
use Illuminate\Support\Collection;

/**
 * Trạng thái hiển thị của một quyền trong trang nhóm quyền, dùng chung cho
 * form chỉnh sửa (checkbox) và trang chi tiết chỉ xem.
 */
class RolePermissionDisplay
{
    public const INTERNAL_DEFAULTS = ['MESSAGES_VIEW', 'MESSAGES_CREATE', 'NOTIFICATION_VIEW', 'NOTIFICATIONS_VIEW'];

    /**
     * @param  Collection<int, int>  $assignedPermissionIds
     * @return array{granted: bool, default: bool, excluded: bool, mobileOnly: bool, ownerOnly: bool}
     */
    public static function state(Quyen $permission, VaiTro $role, Collection $assignedPermissionIds): array
    {
        $ownerOnly = in_array($permission->MaQuyen, QuyenMapper::ownerOnlyMaQuyens(), true);
        $customerGroup = InternalCommunicationPolicy::isCustomerRole($role->TenVaiTro);
        $default = ! $customerGroup && in_array($permission->MaQuyen, self::INTERNAL_DEFAULTS, true);
        $excluded = $customerGroup && InternalCommunicationPolicy::handles(strtolower(str_replace('_', '.', $permission->MaQuyen)));
        $mobileOnly = $excluded && in_array($permission->MaQuyen, ['NOTIFICATION_VIEW', 'NOTIFICATIONS_VIEW'], true);

        return [
            'granted' => $default || ($assignedPermissionIds->contains((int) $permission->getKey()) && (! $excluded || $mobileOnly)),
            'default' => $default,
            'excluded' => $excluded,
            'mobileOnly' => $mobileOnly,
            'ownerOnly' => $ownerOnly,
        ];
    }
}
