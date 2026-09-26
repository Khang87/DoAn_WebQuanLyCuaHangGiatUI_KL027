<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

    #[Fillable(['name', 'email', 'password', 'role', 'role_id', 'phone', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Giữ cho `role_id` luôn khớp với cột `role` legacy.
     * Nhờ vậy việc tạo tài khoản từ form, factory hay seeder đều tự động
     * có vai trò động mà không phải gán thủ công.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (! $user->role) {
                return;
            }

            if (! Schema::hasTable('roles')) {
                return;
            }

            $slug = Role::normalizeSlug($user->role);

            if ($slug === null) {
                return;
            }

            $roleId = Role::query()->where('slug', $slug)->value('id');

            // Luôn đồng bộ lại: nếu cột `role` đổi (vd employee -> manager) thì
            // `role_id` cũ cũng phải theo, nếu không tài khoản sẽ giữ vai trò cũ.
            if ($roleId && $user->role_id !== $roleId) {
                $user->role_id = $roleId;
            }
        });
    }

    /**
     * Đơn hàng do nhân viên này phụ trách.
     *
     * Khoá ngoại là `orders.employee_id`, không phải `user_id` — hasMany()
     * không tự đoán đúng nên phải chỉ định tường minh, nếu không sẽ sinh
     * SQL sai và làm trang chi tiết tài khoản lỗi 500.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'employee_id');
    }

    /**
     * Phiếu giao nhận do nhân viên này phụ trách (khoá ngoại `employee_id`).
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'employee_id');
    }

    /**
     * Vai trò động của tài khoản (bảng roles).
     * Cột `role` cũ vẫn được giữ để tương thích với các kiểm tra vai trò
     * đã có (isManager/isStaff/...), còn `role_id` là nguồn cho phân quyền động.
     */
    public function roleRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Kiểm tra quyền động của tài khoản.
     *
     * Chủ cửa hàng luôn có mọi quyền. Các vai trò khác chỉ có những mã quyền
     * được gán trong bảng `role_has_permissions`; quyền đặc biệt tài chính /
     * cấu hình phân quyền không thể gán cho vai trò khác ngoài Chủ cửa hàng.
     */
    public function canPermission(string $code): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $role = $this->relationLoaded('roleRole')
            ? $this->roleRole
            : $this->roleRole()->with('permissions')->first();

        if (! $role || ! $role->mayHold($code)) {
            return false;
        }

        return $role->permissions->contains('code', $code);
    }

    /**
     * Slug vai trò chuẩn của tài khoản, đã quy về bảng `roles`.
     * Ưu tiên `role_id`; nếu chưa gán thì suy ra từ cột `role` legacy.
     */
    public function roleSlug(): ?string
    {
        $slug = $this->roleRole?->slug ?? Role::normalizeSlug($this->role);

        return $slug;
    }

    /**
     * Kiểm tra vai trò theo tên gọi quen thuộc.
     *
     * Dùng được cả mã legacy lẫn slug chuẩn:
     *   $user->hasRole('admin')   // Chủ cửa hàng
     *   $user->hasRole('manager') // Quản lý
     *   $user->hasRole('staff')   // Nhân viên (gồm cả 'employee')
     *
     * @param  string|list<string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $wanted = array_map(
            fn (string $role) => Role::normalizeSlug($role),
            (array) $roles,
        );

        return in_array($this->roleSlug(), $wanted, true);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::OWNER_SLUG);
    }

    public function isManager(): bool
    {
        return $this->hasRole(['manager', 'owner']);
    }

    public function isAdmin(): bool
    {
        return $this->isManager();
    }

    public function isStaff(): bool
    {
        return $this->hasRole(['manager', 'staff', 'owner']);
    }

    public function isEmployee(): bool
    {
        return $this->hasRole('staff');
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function getAvatarUrlAttribute(): string
    {
        if (! empty($this->avatar) && file_exists(public_path($this->avatar))) {
            return asset($this->avatar);
        }

        return asset('assets/images/user_' . (($this->id % 8) + 1) . '.jpg');
    }
}
