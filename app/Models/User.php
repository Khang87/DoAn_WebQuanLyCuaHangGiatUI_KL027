<?php

namespace App\Models;

use App\Support\PermissionCache;
use App\Support\QuyenMapper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

/**
 * Tài khoản đăng nhập, ánh xạ tới bảng `TaiKhoan` của Supabase.
 *
 * Cột trong DB đều viết tiếng Việt và hoa chữ đầu (vd `MatKhau`), nên model phải
 * khai báo đúng tên cột cho từng thành phần xác thực:
 *   - khoá chính      : TaiKhoanID
 *   - mật khẩu        : MatKhau   (xem getAuthPasswordName())
 *   - email đăng nhập : Email
 *   - tên hiển thị    : TenDangNhap
 *
 * Bảng không có created_at/updated_at/deleted_at nên timestamps bị tắt và trạng
 * thái khóa dùng cột `TrangThai` thay cho soft delete.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'TaiKhoan';

    protected $primaryKey = 'TaiKhoanID';

    /** TaiKhoanID là serial của PostgreSQL, không phải UUID. */
    protected $keyType = 'int';

    public $incrementing = true;

    /** Bảng không có cột thời gian. */
    public $timestamps = false;

    /** Giữ nguyên tên cột tiếng Việt khi sinh SQL. */
    public static $snakeAttributes = false;

    protected $fillable = [
        'TenDangNhap',
        'MatKhau',
        'Email',
        'SoDienThoai',
        'NhanVienID',
        'KhachHangID',
        'TrangThai',
        'NgayTao',
        'TaiKhoanID',
        'UserAuthId',
        'AvatarURL',
    ];

    protected $hidden = [
        'MatKhau',
    ];

    protected function casts(): array
    {
        return [
            'NhanVienID' => 'integer',
            'KhachHangID' => 'integer',
            'NgayTao' => 'datetime',
        ];
    }

    /**
     * Mật khẩu nằm ở cột `MatKhau`, không phải `password` như mặc định của
     * framework. Không khai báo đúng chỗ này thì `Auth::attempt()` và
     * `Hash::check()` sẽ luôn thất bại.
     */
    public function getAuthPasswordName(): string
    {
        return 'MatKhau';
    }

    public function getAuthPassword()
    {
        return $this->attributes['MatKhau'] ?? null;
    }

    public function getEmailForPasswordReset(): ?string
    {
        return $this->attributes['Email'] ?? null;
    }

    /* ---------------------------------------------------------------------
     | Quan hệ
     |--------------------------------------------------------------------*/

    public function vaiTros(): BelongsToMany
    {
        return $this->belongsToMany(
            VaiTro::class,
            'TaiKhoan_VaiTro',
            'TaiKhoanID',
            'VaiTroID',
        );
    }

    public function nhanVien(): BelongsTo
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID');
    }

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }

    public function thongBaos(): HasMany
    {
        return $this->hasMany(ThongBao::class, 'TaiKhoanID');
    }

    /**
     * Ghi đè quan hệ `notifications()` mặc định của trait `Notifiable`
     * (trỏ tới bảng `notifications` không tồn tại trong schema tiếng Việt)
     * để trỏ về bảng `ThongBao`. View dùng `read_at` được ánh xạ qua accessor của ThongBao.
     */
    public function notifications(): HasMany
    {
        return $this->thongBaos();
    }

    /* ---------------------------------------------------------------------
     | Thuận tiện cho code cũ đang dùng tên cột tiếng Anh
     |--------------------------------------------------------------------*/

    /**
     * Chuẩn hoá tên vai trò trong bảng `VaiTro` về slug dùng trong code.
     *
     * @var array<string, string>
     */
    private const ROLE_SLUGS = [
        'chủ cửa hàng' => 'owner',
        'chu cua hang' => 'owner',
        'chủ cua hàng' => 'owner',
        'owner' => 'owner',
        'admin' => 'owner',
        'quản lý' => 'manager',
        'quan ly' => 'manager',
        'quanly' => 'manager',
        'manager' => 'manager',
        'nhân viên' => 'staff',
        'nhan vien' => 'staff',
        'nhanvien' => 'staff',
        'staff' => 'staff',
        'employee' => 'staff',
        'khách hàng' => 'customer',
        'khach hang' => 'customer',
        'khachhang' => 'customer',
        'customer' => 'customer',
    ];

    public function getNameAttribute(): string
    {
        return (string) ($this->nhanVien?->HoTen ?: $this->khachHang?->HoTen ?: $this->TenDangNhap);
    }

    public function getEmailAttribute(): ?string
    {
        // Đọc thẳng mảng thuộc tính: `Str::studly('Email')` ra đúng `'Email'`
        // nên accessor này trùng tên với cột thật, `$this->Email` sẽ gọi
        // đệ quy vào chính nó.
        return $this->attributes['Email'] ?? null;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->nhanVien?->SoDienThoai
            ?? $this->khachHang?->SoDienThoai
            ?? ($this->attributes['SoDienThoai'] ?? null);
    }

    public function getAvatarUrlAttribute(): string
    {
        $storedAvatarUrl = $this->attributes['AvatarURL'] ?? null;
        if (filled($storedAvatarUrl)) {
            return $storedAvatarUrl;
        }

        $seed = ($this->getKey() % 8) + 1;
        $avatarDirectory = public_path('uploads/avatars');
        $avatarFiles = glob($avatarDirectory.DIRECTORY_SEPARATOR.'avatar_'.$this->getKey().'.*') ?: [];

        foreach ($avatarFiles as $avatarFile) {
            if (is_file($avatarFile)) {
                return asset('uploads/avatars/'.basename($avatarFile)).'?v='.filemtime($avatarFile);
            }
        }

        if (
            filled(config('filesystems.disks.supabase.key'))
            && filled(config('filesystems.disks.supabase.secret'))
            && filled(config('filesystems.disks.supabase.bucket'))
            && filled(config('filesystems.disks.supabase.endpoint'))
            && filled(config('filesystems.disks.supabase.url'))
        ) {
            return Storage::disk('supabase')->url('avatars/'.$this->getKey());
        }

        return asset('assets/images/user_'.$seed.'.jpg');
    }

    /** Cột `role` cũ vẫn được nhiều middleware so sánh trực tiếp. */
    public function getRoleAttribute(): string
    {
        $slug = $this->roleSlug();

        return $slug === 'owner' ? 'admin' : ($slug ?? '');
    }

    /* ---------------------------------------------------------------------
     | Vai trò
     |--------------------------------------------------------------------*/

    /**
     * Slug vai trò chuẩn của tài khoản.
     *
     * Một tài khoản có thể mang nhiều vai trò (VD nhân viên kiêm khách hàng),
     * nên ưu tiên theo thứ tự quyền cao: Chủ cửa hàng > Quản lý > Nhân viên.
     *
     * Đọc từ {@see PermissionCache} nên không query lại `TaiKhoan_VaiTro`/`VaiTro`
     * ở mỗi lần gọi trong cùng một request.
     */
    public function roleSlug(): ?string
    {
        $slugs = collect($this->roleNames())
            ->map(fn (string $tenVaiTro) => self::ROLE_SLUGS[mb_strtolower(trim($tenVaiTro))]
                ?? VaiTro::slugForName($tenVaiTro))
            ->values();

        foreach (['owner', 'manager', 'staff', 'customer'] as $priority) {
            if ($slugs->contains($priority)) {
                return $priority;
            }
        }

        return $slugs->first();
    }

    /**
     * @param  string|list<string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $slug = $this->roleSlug();

        return $slug !== null && in_array($slug, (array) $roles, true);
    }

    public function isOwner(): bool
    {
        return $this->isActive() && $this->hasRole('owner');
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

    public function isActive(): bool
    {
        return $this->TrangThai === 'Hoạt động';
    }

    /* ---------------------------------------------------------------------
     | Phân quyền
     |
     | Mã quyền trong bảng `Quyen` viết dạng SCREAMING_SNAKE (`ORDER_VIEW`), còn
     | mã dùng trong Blade/Controller viết dạng dot.lowercase (`orders.view`).
     | Việc quy đổi và cache giaom cho {@see QuyenMapper} và {@see PermissionCache}.
     |--------------------------------------------------------------------*/

    /**
     * Tên vai trò (TenVaiTro) đang được cấp cho tài khoản.
     *
     * @return list<string>
     */
    public function roleNames(): array
    {
        return PermissionCache::roles($this->getKey());
    }

    /**
     * Mã quyền (MaQuyen) đang được cấp cho tài khoản.
     *
     * @return list<string>
     */
    public function maQuyens(): array
    {
        return PermissionCache::quyens($this->getKey());
    }

    /**
     * Mã quyền dạng dùng trong code (`orders.view`) mà tài khoản thực sự có.
     *
     * Quyền `*_MANAGE` trong bảng `Quyen` được hiểu là cấp trọn module, nên
     * `SERVICE_MANAGE` cho qua mọi mã `services.*` và `service_categories.*`.
     *
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        $codes = QuyenMapper::coveredCodes($this->maQuyens());

        if ($this->isOwner()) {
            return array_values(array_unique([
                ...$codes,
                ...array_map(
                    static fn (string $permission): string => strtolower(str_replace('_', '.', $permission)),
                    $this->maQuyens(),
                ),
            ]));
        }

        $dynamicCodes = array_map(
            static fn (string $permission): string => strtolower(str_replace('_', '.', $permission)),
            $this->maQuyens(),
        );

        return array_values(array_unique(array_filter(
            [...$codes, ...$dynamicCodes],
            fn (string $code) => ! QuyenMapper::isOwnerOnly($code),
        )));
    }

    /**
     * Kiểm tra một mã quyền dạng dùng trong code.
     *
     * Chủ cửa hàng có toàn quyền, kể cả quyền chưa tồn tại trong `PermissionRegistry`
     * (đã được Gate::before cho qua trước, nhưng middleware gọi thẳng hàm này).
     */
    public function canPermission(string $code): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isOwner()) {
            return true;
        }

        if (QuyenMapper::isOwnerOnly($code)) {
            return false;
        }

        $grantedPermissions = $this->maQuyens();

        if (QuyenMapper::covers($code, $grantedPermissions)) {
            return true;
        }

        $directPermissionCode = strtoupper(str_replace('.', '_', $code));

        return in_array($directPermissionCode, $grantedPermissions, true);
    }

    /* ---------------------------------------------------------------------
     | Khoá / mở tài khoản
     |
     | Bảng không dùng soft delete nên trạng thái nằm ở cột `TrangThai`.
     |--------------------------------------------------------------------*/

    public function trashed(): bool
    {
        return ! $this->isActive();
    }

    public function restore(): void
    {
        $this->TrangThai = 'Hoạt động';
        $this->save();
    }
}
