<?php

namespace App\Support;

use App\Models\VaiTro;
use Illuminate\Database\Eloquent\Builder;

/** Defaults for the admin web only; customer/mobile permissions stay in the database. */
final class InternalCommunicationPolicy
{
    private const CUSTOMER_NAMES = ['khách hàng', 'khach hang', 'khachhang', 'khach-hang', 'customer', 'client'];

    public const DEFAULTS = ['messages.view', 'messages.create', 'notifications.view'];

    public static function handles(string $code): bool
    {
        return str_starts_with($code, 'messages.') || str_starts_with($code, 'notifications.')
            || str_starts_with($code, 'notification.');
    }

    public static function isCustomerRole(string $name): bool
    {
        return in_array(VaiTro::slugForName($name), ['khach-hang', 'khachhang', 'customer', 'client'], true);
    }

    public static function scopeAccounts(Builder $query): Builder
    {
        return $query->where('TrangThai', 'Hoạt động')
            ->whereHas('vaiTros', fn ($roles) => $roles->where('TrangThai', 'Hoạt động'))
            ->whereDoesntHave('vaiTros', fn ($roles) => $roles->where('TrangThai', 'Hoạt động')
                ->whereRaw('lower(trim("VaiTro"."TenVaiTro")) in ('.implode(',', array_fill(0, count(self::CUSTOMER_NAMES), '?')).')', self::CUSTOMER_NAMES));
    }

    /** @param list<string> $roles */
    public static function allowsRoles(array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        foreach ($roles as $role) {
            if (self::isCustomerRole($role)) {
                return false;
            }
        }

        return true;
    }
}
