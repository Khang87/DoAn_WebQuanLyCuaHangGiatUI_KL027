<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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
        return (string) ($this->nhanVien?->HoTen ?: $this->TenDangNhap);
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
        return $this->SoDienThoai;
    }

    public function getAvatarUrlAttribute(): string
    {
        $seed = ($this->getKey() % 8) + 1;

        return asset('assets/images/user_' . $seed . '.jpg');
    }

    /** Cột `role` cũ vẫn được nhiều middleware so sánh trực tiếp. */
    public function getRoleAttribute(): string
    {
        $slug = $this->roleSlug();

        return $slug === 'owner' ? 'admin' : ($slug ?? 'customer');
    }

    /* ---------------------------------------------------------------------
     | Vai trò
     |--------------------------------------------------------------------*/

    /**
     * Slug vai trò chuẩn của tài khoản.
     *
     * Một tài khoản có thể mang nhiều vai trò (VD nhân viên kiêm khách hàng),
     * nên ưu tiên theo thứ tự quyền cao: Chủ cửa hàng > Quản lý > Nhân viên.
     */
    public function roleSlug(): ?string
    {
        $slugs = $this->vaiTros
            ->map(fn (VaiTro $vaiTro) => self::ROLE_SLUGS[mb_strtolower(trim((string) $vaiTro->TenVaiTro))] ?? null)
            ->filter()
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
        return $this->hasRole('owner');
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
     |--------------------------------------------------------------------*/

    /**
     * Mã quyền trong bảng `Quyen` viết dạng SCREAMING_SNAKE (`ORDER_VIEW`), còn
     * mã trong PermissionRegistry viết dạng dot.lowercase (`orders.view`).
     */

    /**
     * Danh sách mã quyền (dạng SCREAMING_SNAKE) đang được cấp cho tài khoản.
     *
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        return VaiTroQuyen::query()
            ->join('Quyen', 'Quyen.QuyenID', '=', 'VaiTro_Quyen.QuyenID')
            ->join('TaiKhoan_VaiTro', 'TaiKhoan_VaiTro.VaiTroID', '=', 'VaiTro_Quyen.VaiTroID')
            ->where('TaiKhoan_VaiTro.TaiKhoanID', $this->getKey())
            ->where('Quyen.TrangThai', 'Hoạt động')
            ->pluck('Quyen.MaQuyen')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Tiền tố module trong `PermissionRegistry` => tiền tố mã quyền trong `Quyen`.
     *
     * Registry dùng số nhiều (`orders`, `services`) còn bảng `Quyen` dùng số ít
     * (`ORDER_VIEW`, `SERVICE_MANAGE`), nên phải ánh xạ thay vì chỉ `strtoupper`.
     *
     * @var array<string, string>
     */
    private const MODULE_PREFIXES = [
        'orders' => 'ORDER',
        'invoices' => 'INVOICE',
        'payments' => 'PAYMENT',
        'services' => 'SERVICE',
        'service_categories' => 'SERVICE',
        'customers' => 'CUSTOMER',
        'deliveries' => 'DELIVERY',
        'bookings' => 'BOOKING',
        'garments' => 'GARMENT',
        'garment_conditions' => 'GARMENT_CONDITION',
        'pricings' => 'PRICE',
        'promotions' => 'PROMOTION',
        'coupons' => 'COUPON',
        'reviews' => 'REVIEW',
        'notifications' => 'NOTIFICATION',
        'reports' => 'REPORT',
        'accounts' => 'ACCOUNT',
        'settings' => 'SETTINGS',
        'roles' => 'ROLE',
        'dashboard' => 'DASHBOARD',
    ];

    /**
     * Hành động trong `PermissionRegistry` => hậu tố mã quyền trong bảng `Quyen`.
     *
     * `orders.view` -> `ORDER_VIEW`, `orders.edit` -> `ORDER_UPDATE`, ...
     *
     * @var array<string, string>
     */
    private const ACTION_SUFFIXES = [
        'view' => 'VIEW',
        'create' => 'CREATE',
        'edit' => 'UPDATE',
        'delete' => 'DELETE',
        'update_status' => 'UPDATE',
        'manage' => 'MANAGE',
    ];

    /**
     * Kiểm tra một mã quyền.
     *
     * Chủ cửa hàng có toàn quyền. Mã `*_MANAGE` trong bảng `Quyen` được hiểu là
     * cấp quyền cho cả module, nên `SERVICE_MANAGE` cho qua mọi mã `services.*`.
     */
    public function canPermission(string $code): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $granted = $this->permissionCodes();

        if (in_array('SYSTEM_FULL_ACCESS', $granted, true)) {
            return true;
        }

        [$module, $action] = array_pad(explode('.', $code, 2), 2, 'view');

        $prefix = self::MODULE_PREFIXES[$module] ?? strtoupper($module);
        $suffix = self::ACTION_SUFFIXES[$action] ?? null;

        // Quyền `*_MANAGE` bao trùm cả module.
        if (in_array($prefix . '_MANAGE', $granted, true)) {
            return true;
        }

        if ($suffix === null) {
            return false;
        }

        return in_array($prefix . '_' . $suffix, $granted, true);
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

    public function delete()
    {
        $this->TrangThai = 'Đã khóa';
        $this->save();

        return true;
    }

    public function restore(): void
    {
        $this->TrangThai = 'Hoạt động';
        $this->save();
    }
}
