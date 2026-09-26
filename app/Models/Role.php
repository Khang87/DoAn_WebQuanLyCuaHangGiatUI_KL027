<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description'])]
class Role extends Model
{
    use HasFactory;

    /**
     * Các mã quyền chỉ vai trò Chủ cửa hàng được phép sở hữu.
     * Đây là ràng buộc nghiệp vụ bắt buộc: Nhân viên & Quản lý không bao giờ
     * được cấp quyền sửa/xóa đơn đã quyết toán hoặc hoàn tiền.
     */
    public const OWNER_ONLY_PERMISSIONS = [
        'orders.edit_completed',
        'orders.delete_completed',
        'invoices.edit_paid',
        'invoices.delete_paid',
        'orders.refund',
        'payments.refund',
        'payments.edit_paid',
        'payments.delete_paid',
        'roles.manage',
    ];

    /** Vai trò Chủ cửa hàng - vai trò duy nhất sở hữu đặc quyền tài chính. */
    public const OWNER_SLUG = 'owner';

    /**
     * Chuẩn hoá mã vai trò legacy (cột `users.role`) về slug trong bảng `roles`.
     * Cột `role` còn tồn tại các giá trị lịch sử không có vai trò tương ứng;
     * nếu không ánh xạ, tài khoản sẽ rơi về `customer` và mất toàn bộ quyền.
     *
     * @var array<string, string>
     */
    public const LEGACY_SLUG_ALIASES = [
        'admin' => self::OWNER_SLUG,
        'owner' => self::OWNER_SLUG,
        'manager' => 'manager',
        'staff' => 'staff',
        'employee' => 'staff',
        'customer' => 'customer',
    ];

    /**
     * Đổi mã vai trò legacy thành slug chuẩn của bảng `roles`.
     * Trả về null nếu không xác định được.
     */
    public static function normalizeSlug(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        return self::LEGACY_SLUG_ALIASES[$role] ?? null;
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isOwner(): bool
    {
        return $this->slug === self::OWNER_SLUG;
    }

    /**
     * Vai trò này có được phép giữ quyền $code hay không.
     * Quyền đặc biệt (tài chính / cấu hình phân quyền) chỉ Chủ cửa hàng được giữ.
     */
    public function mayHold(string $code): bool
    {
        if (! in_array($code, self::OWNER_ONLY_PERMISSIONS, true)) {
            return true;
        }

        return $this->isOwner();
    }
}
