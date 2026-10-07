<?php

namespace App\Support;

/**
 * Cầu nối hai hệ thống đặt tên quyền:
 *
 *   - Mã quyền trong code / giao diện: `orders.view`  (App\Support\PermissionRegistry)
 *   - Mã quyền lưu trên Supabase:      `ORDER_VIEW`   (Quyen.MaQuyen)
 *
 * Chỉ mã được khai báo trong registry và có ánh xạ được chấp nhận để gán.
 * Quyền mới phải được nhà phát triển thêm vào registry và mapper trước khi sử dụng.
 *
 * `resolveMaQuyen()` và `covers()` là hai chiều đảo của nhau: một mã quyền được
 * coi là "có" khi và chỉ khi mã `MaQuyen` mà nó quy đổi ra nằm trong danh sách
 * quyền được cấp. Nhờ vậy ma trận phân quyền tô sẵn ô tích chọn đúng bằng thứ
 * mà lúc runtime thực sự kiểm tra.
 */
final class QuyenMapper
{
    /** Mã toàn quyền trong bảng `Quyen`, không có mã tương ứng trong registry. */
    public const FULL_ACCESS = 'SYSTEM_FULL_ACCESS';

    /**
     * Các mã quyền mặc định của ứng dụng.
     *
     * Các mã này giữ tương thích với dữ liệu quyền đã có. Quyền mới cần được
     * thêm vào danh sách này và PermissionRegistry bởi nhà phát triển.
     *
     * @var list<string>
     */
    public const KNOWN_MAQUYEN = [
        'DASHBOARD_VIEW',
        'NOTIFICATION_VIEW',
        'ORDER_VIEW',
        'ORDER_CREATE',
        'ORDER_UPDATE',
        'ORDER_DELETE',
        'SERVICE_MANAGE',
        'PRICE_MANAGE',
        'PROMOTION_MANAGE',
        'CUSTOMER_VIEW',
        'CUSTOMER_MANAGE',
        'DELIVERY_MANAGE',
        'REPORT_VIEW',
        'INVOICE_VIEW',
        'INVOICE_UPDATE',
        'PAYMENT_CREATE',
        'ACCOUNT_MANAGE',
        'ROLE_MANAGE',
        self::FULL_ACCESS,
        'MESSAGES_VIEW',
        'MESSAGES_CREATE',
        'SYSTEM_LOGS_VIEW',
        'ACCOUNTING_VIEW',
    ];

    /**
     * Mã quyền chỉ Chủ cửa hàng được giữ (giữ nguyên danh sách mà
     * RoleAndPermissionSeeder dùng để nạp dữ liệu, tránh lệch với DB).
     *
     * @var list<string>
     */
    public const OWNER_ONLY_MAQUYEN = [
        'ORDER_DELETE',
        'INVOICE_UPDATE',
        'ACCOUNT_MANAGE',
        'ROLE_MANAGE',
        self::FULL_ACCESS,
    ];

    /**
     * Quyền giao diện chỉ Chủ cửa hàng được dùng nhưng bảng `Quyen` không có mã
     * riêng, nên quyền này được ép về Chủ cửa hàng bằng vai trò chứ không ghi
     * xuống DB.
     *
     * @var list<string>
     */
    public const OWNER_ONLY_CODES = [
        'orders.edit_completed',
        'orders.delete_completed',
        'orders.refund',
        'invoices.edit_paid',
        'invoices.delete_paid',
        'payments.edit_paid',
        'payments.delete_paid',
        'payments.refund',
        'roles.manage',
    ];

    /**
     * Ghi đè theo từng mã code, ưu tiên cao nhất.
     *
     * Dùng khi mã quyền trong DB phản ánh một hành động riêng chứ không phải
     * toàn module (vd `reports.revenue` chỉ có một mã `REPORT_VIEW` chung với
     * `reports.view`).
     *
     * @var array<string, string>
     */
    private const CODE_MAQUYEN = [
        'reports.revenue' => 'REPORT_VIEW',
        'payments.view' => 'PAYMENT_CREATE',
        'payments.edit' => 'PAYMENT_CREATE',
    ];

    /**
     * Module legacy chỉ có một mã quyền duy nhất trong bảng `Quyen`, dùng mã đó cho mọi
     * hành động của module thay vì để trống.
     *
     * Snapshot hiện tại có các mã gộp, nên phần lớn module vận hành dùng chung
     * mã `*_MANAGE` hiện có. Không có ánh xạ này thì các
     * route/menu như `garment-categories`, `bookings`, `coupons` sẽ bị chặn
     * với mọi tài khoản trừ Chủ cửa hàng.
     *
     * @var array<string, string>
     */
    private const MODULE_MAQUYEN = [
        'garment_conditions' => 'SERVICE_MANAGE',
        'garment_categories' => 'SERVICE_MANAGE',
        'bookings' => 'DELIVERY_MANAGE',
        'coupons' => 'PROMOTION_MANAGE',
    ];

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
        'garment_conditions' => 'GARMENT_CONDITION',
        'garment_categories' => 'GARMENT_CATEGORY',
        'pricings' => 'PRICE',
        'promotions' => 'PROMOTION',
        'coupons' => 'COUPON',
        'reviews' => 'REVIEW',
        'notifications' => 'NOTIFICATION',
        'reports' => 'REPORT',
        'accounts' => 'ACCOUNT',
        'roles' => 'ROLE',
        'dashboard' => 'DASHBOARD',
    ];

    /**
     * Quyền cần mã riêng trong catalog mới, không dùng chung quyền quản lý cũ.
     *
     * @var array<string, string>
     */
    private const GRANULAR_MAQUYEN = [
        'messages.view' => 'MESSAGES_VIEW',
        'messages.create' => 'MESSAGES_CREATE',
        'system_logs.view' => 'SYSTEM_LOGS_VIEW',
        'accounting.view' => 'ACCOUNTING_VIEW',
    ];

    /**
     * Hành động trong `PermissionRegistry` => hậu tố mã quyền trong bảng `Quyen`.
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

    /** @var array<string, string>|null cache trong tiến trình */
    private static ?array $forwardCache = null;

    /** @var array<string, list<string>>|null */
    private static ?array $reverseCache = null;

    /**
     * Mã quyền trong DB ứng với từng mã quyền dùng trong code.
     *
     * Giá trị null nghĩa là mã code đó không có mã quyền tương ứng trên Supabase.
     *
     * @return array<string, string|null> mã code => MaQuyen
     */
    public static function forward(): array
    {
        if (self::$forwardCache !== null) {
            return self::$forwardCache;
        }

        $map = [];

        foreach (PermissionRegistry::codes() as $code) {
            $map[$code] = self::resolveMaQuyen($code);
        }

        return self::$forwardCache = $map;
    }

    /**
     * Chiều ngược: một `MaQuyen` bao trùm những mã code nào.
     *
     * @return array<string, list<string>> MaQuyen => danh sách mã code
     */
    public static function reverse(): array
    {
        if (self::$reverseCache !== null) {
            return self::$reverseCache;
        }

        $map = [];

        foreach (self::forward() as $code => $maQuyen) {
            if ($maQuyen === null) {
                continue;
            }

            $map[$maQuyen][] = $code;
        }

        return self::$reverseCache = $map;
    }

    /**
     * Quy đổi mã quyền trong code sang mã `MaQuyen` cần lưu trong DB.
     *
     * Thứ tự ưu tiên:
     *   1. `CODE_MAQUYEN` cho hành động có mã riêng;
     *   2. quy đổi trực tiếp `module` + `hành động` nếu mã đó có trong catalog;
     *   3. `MODULE_MAQUYEN` khi module chỉ có một mã quyền duy nhất
     *      (`garment_categories.*` -> `SERVICE_MANAGE`);
     *   4. mã `MODULE_MANAGE` suy ra từ tiền tố (`SERVICE_VIEW` -> `SERVICE_MANAGE`);
     *   5. null — không có mã quyền được cấu hình tương ứng.
     */
    public static function resolveMaQuyen(string $code): ?string
    {
        $module = explode('.', $code, 2)[0];

        $candidates = [
            self::CODE_MAQUYEN[$code] ?? null,
            self::GRANULAR_MAQUYEN[$code] ?? null,
            self::toMaQuyen($code),
            self::MODULE_MAQUYEN[$module] ?? null,
            (self::MODULE_PREFIXES[$module] ?? strtoupper($module)).'_MANAGE',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && in_array($candidate, self::KNOWN_MAQUYEN, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Quy đổi trực tiếp mã code sang `MaQuyen`, chưa xét tới danh sách mã đang có.
     */
    public static function toMaQuyen(string $code): string
    {
        if (isset(self::GRANULAR_MAQUYEN[$code])) {
            return self::GRANULAR_MAQUYEN[$code];
        }

        [$module, $action] = array_pad(explode('.', $code, 2), 2, 'view');

        $prefix = self::MODULE_PREFIXES[$module] ?? strtoupper($module);
        $suffix = self::ACTION_SUFFIXES[$action] ?? null;

        return $suffix === null ? $prefix : $prefix.'_'.$suffix;
    }

    /**
     * Mã quyền này có mã tương ứng trong bảng `Quyen` hay không.
     *
     * Chỉ trả về true cho mã quyền được cấu hình trong ứng dụng.
     */
    public static function isResolvable(string $code): bool
    {
        return self::resolveMaQuyen($code) !== null;
    }

    /**
     * Danh sách `MaQuyen` mà tài khoản giữ có đủ để chạy mã code này hay không.
     *
     * @param  list<string>  $grantedMaQuyen
     */
    public static function covers(string $code, array $grantedMaQuyen): bool
    {
        if (in_array(self::FULL_ACCESS, $grantedMaQuyen, true)) {
            return true;
        }

        $maQuyen = self::resolveMaQuyen($code);

        return $maQuyen !== null && in_array($maQuyen, $grantedMaQuyen, true);
    }

    /**
     * Danh sách mã code mà một vai trò đang thực sự có, tính cả quyền được
     * `*_MANAGE` bao trùm.
     *
     * @param  list<string>  $grantedMaQuyen
     * @return list<string>
     */
    public static function coveredCodes(array $grantedMaQuyen): array
    {
        $grantedMaQuyen = array_values(array_unique($grantedMaQuyen));

        return array_values(array_filter(
            PermissionRegistry::codes(),
            fn (string $code) => self::covers($code, $grantedMaQuyen),
        ));
    }

    /**
     * Mã code này có bị giới hạn cho Chủ cửa hàng hay không.
     *
     * Đúng khi mã code nằm trong `OWNER_ONLY_CODES` (quyền không có mã riêng
     * trong DB) hoặc quy đổi ra một `MaQuyen` chỉ Chủ cửa hàng giữ.
     */
    public static function isOwnerOnly(string $code): bool
    {
        if (in_array($code, self::OWNER_ONLY_CODES, true)) {
            return true;
        }

        return self::isOwnerOnlyMaQuyen(self::resolveMaQuyen($code) ?? '');
    }

    public static function isOwnerOnlyMaQuyen(string $maQuyen): bool
    {
        return in_array($maQuyen, self::OWNER_ONLY_MAQUYEN, true);
    }

    /**
     * @return list<string>
     */
    public static function ownerOnlyMaQuyens(): array
    {
        return self::OWNER_ONLY_MAQUYEN;
    }

    /**
     * Danh sách mã code mà vai trò khác Chủ cửa hàng không được giữ.
     *
     * @return list<string>
     */
    public static function ownerOnlyCodes(): array
    {
        return array_values(array_filter(
            PermissionRegistry::codes(),
            fn (string $code) => self::isOwnerOnly($code),
        ));
    }
}
