<?php

namespace Tests\Unit;

use App\Support\PermissionRegistry;
use App\Support\QuyenMapper;
use PHPUnit\Framework\TestCase;

/**
 * Ánh xạ hai chiều giữa mã quyền trong code (`orders.view`) và mã quyền trên
 * Supabase (`Quyen.MaQuyen` = `ORDER_VIEW`).
 *
 * Không dùng RefreshDatabase: các ca kiểm tra này chỉ đọc hằng số trong
 * {@see QuyenMapper} nên không đụng tới database nào.
 */
class QuyenMapperTest extends TestCase
{
    public function test_it_maps_a_code_to_the_matching_ma_quyen(): void
    {
        $this->assertSame('ORDER_VIEW', QuyenMapper::resolveMaQuyen('orders.view'));
        $this->assertSame('ORDER_CREATE', QuyenMapper::resolveMaQuyen('orders.create'));
        $this->assertSame('ORDER_UPDATE', QuyenMapper::resolveMaQuyen('orders.edit'));
        $this->assertSame('ORDER_UPDATE', QuyenMapper::resolveMaQuyen('orders.update_status'));
        $this->assertSame('CUSTOMER_VIEW', QuyenMapper::resolveMaQuyen('customers.view'));
        $this->assertSame('REPORT_VIEW', QuyenMapper::resolveMaQuyen('reports.revenue'));
    }

    public function test_it_falls_back_to_the_manage_code_when_the_exact_one_is_absent(): void
    {
        // Bảng `Quyen` chỉ có `SERVICE_MANAGE`, không có `SERVICE_VIEW`.
        $this->assertSame('SERVICE_MANAGE', QuyenMapper::resolveMaQuyen('services.view'));
        $this->assertSame('SERVICE_MANAGE', QuyenMapper::resolveMaQuyen('service_categories.edit'));
        $this->assertSame('PRICE_MANAGE', QuyenMapper::resolveMaQuyen('pricings.view'));
        $this->assertSame('CUSTOMER_MANAGE', QuyenMapper::resolveMaQuyen('customers.edit'));
    }

    public function test_it_maps_modules_that_only_have_one_ma_quyen(): void
    {
        // Module vận hành dùng chung mã `*_MANAGE` của mình vì bảng `Quyen` chỉ
        // có 18 mã. Không có phép ánh xạ này thì các route/menu này bị chặn với
        // mọi tài khoản trừ Chủ cửa hàng.
        $this->assertSame('SERVICE_MANAGE', QuyenMapper::resolveMaQuyen('garment_conditions.edit'));
        $this->assertSame('SERVICE_MANAGE', QuyenMapper::resolveMaQuyen('garment_categories.view'));
        $this->assertSame('DELIVERY_MANAGE', QuyenMapper::resolveMaQuyen('bookings.edit'));
        $this->assertSame('PROMOTION_MANAGE', QuyenMapper::resolveMaQuyen('coupons.view'));
    }

    public function test_payments_view_and_edit_share_payment_create_but_delete_stays_unresolvable(): void
    {
        // Xoá khoản thu là thao tác tài chính, cố ý không map để chỉ Chủ cửa hàng.
        $this->assertSame('PAYMENT_CREATE', QuyenMapper::resolveMaQuyen('payments.view'));
        $this->assertSame('PAYMENT_CREATE', QuyenMapper::resolveMaQuyen('payments.edit'));
        $this->assertNull(QuyenMapper::resolveMaQuyen('payments.delete'));
        $this->assertFalse(QuyenMapper::isResolvable('payments.delete'));
    }

    public function test_codes_without_any_ma_quyen_are_not_resolvable(): void
    {
        // Bảng `Quyen` không có mã cho các module này nên không cấp được.
        $this->assertNull(QuyenMapper::resolveMaQuyen('reviews.view'));
        $this->assertNull(QuyenMapper::resolveMaQuyen('reviews.respond'));
        $this->assertNull(QuyenMapper::resolveMaQuyen('notifications.create'));
        $this->assertNull(QuyenMapper::resolveMaQuyen('invoices.create'));
        $this->assertNull(QuyenMapper::resolveMaQuyen('garments.view'));
        $this->assertNull(QuyenMapper::resolveMaQuyen('laundry_categories.view'));
        $this->assertFalse(QuyenMapper::isResolvable('notifications.create'));
    }

    public function test_covers_is_the_inverse_of_resolve_ma_quyen(): void
    {
        // Bỏ `SYSTEM_FULL_ACCESS` vì nó cấp được mọi mã kể cả mã không có trong
        // bảng `Quyen`.
        $granted = array_values(array_filter(
            QuyenMapper::KNOWN_MAQUYEN,
            fn (string $maQuyen) => $maQuyen !== QuyenMapper::FULL_ACCESS,
        ));

        foreach (PermissionRegistry::codes() as $code) {
            $maQuyen = QuyenMapper::resolveMaQuyen($code);

            if ($maQuyen === null) {
                $this->assertFalse(
                    QuyenMapper::covers($code, $granted),
                    "{$code} không có mã quyền thì không thể được cấp.",
                );

                continue;
            }

            $this->assertTrue(
                QuyenMapper::covers($code, [$maQuyen]),
                "Cấp {$maQuyen} phải làm cho {$code} chạy được.",
            );
        }
    }

    public function test_a_manage_code_covers_every_action_of_its_module(): void
    {
        $this->assertTrue(QuyenMapper::covers('services.delete', ['SERVICE_MANAGE']));
        $this->assertTrue(QuyenMapper::covers('service_categories.create', ['SERVICE_MANAGE']));
        $this->assertTrue(QuyenMapper::covers('deliveries.view', ['DELIVERY_MANAGE']));
        $this->assertFalse(QuyenMapper::covers('orders.view', ['SERVICE_MANAGE']));
    }

    public function test_full_access_grants_everything(): void
    {
        $this->assertTrue(QuyenMapper::covers('garments.view', [QuyenMapper::FULL_ACCESS]));
        $this->assertTrue(QuyenMapper::covers('payments.refund', [QuyenMapper::FULL_ACCESS]));
    }

    public function test_financial_and_rbac_codes_are_owner_only(): void
    {
        foreach ([
            'orders.delete',
            'orders.edit_completed',
            'orders.delete_completed',
            'orders.refund',
            'invoices.edit_paid',
            'invoices.delete_paid',
            'payments.edit_paid',
            'payments.delete_paid',
            'payments.refund',
            'accounts.edit',
            'roles.manage',
        ] as $code) {
            $this->assertTrue(
                QuyenMapper::isOwnerOnly($code),
                "{$code} phải bị giới hạn cho Chủ cửa hàng.",
            );
        }

        $this->assertFalse(QuyenMapper::isOwnerOnly('orders.view'));
        $this->assertFalse(QuyenMapper::isOwnerOnly('services.view'));
        $this->assertFalse(QuyenMapper::isOwnerOnly('reports.view'));
    }

    public function test_owner_only_database_permissions_are_detected(): void
    {
        $this->assertTrue(QuyenMapper::isOwnerOnlyMaQuyen('ORDER_DELETE'));
        $this->assertTrue(QuyenMapper::isOwnerOnlyMaQuyen(QuyenMapper::FULL_ACCESS));
        $this->assertFalse(QuyenMapper::isOwnerOnlyMaQuyen('ORDER_VIEW'));
    }

    public function test_every_code_maps_to_a_known_ma_quyen(): void
    {
        $known = QuyenMapper::KNOWN_MAQUYEN;

        foreach (QuyenMapper::forward() as $code => $maQuyen) {
            if ($maQuyen === null) {
                // Mã không có mã quyền trên Supabase: giao diện hiển thị ô
                // tích chọn ở trạng thái khoá kèm badge "Chưa có mã quyền".
                $this->assertFalse(QuyenMapper::isResolvable($code));

                continue;
            }

            $this->assertContains(
                $maQuyen,
                $known,
                "Mã code {$code} quy đổi ra mã quyền không có trong bảng Quyen.",
            );
        }
    }

    public function test_known_ma_quyen_that_no_code_uses(): void
    {
        // Mã quyền có trong DB nhưng registry chưa có mã code nào dùng tới,
        // nên không thao tác được trên ma trận. Danh sách này phải được rà lại
        // khi bổ sung quyền vào PermissionRegistry.
        $used = array_values(array_filter(QuyenMapper::forward()));

        $unused = array_values(array_filter(
            QuyenMapper::KNOWN_MAQUYEN,
            fn (string $maQuyen) => $maQuyen !== QuyenMapper::FULL_ACCESS
                && ! in_array($maQuyen, $used, true),
        ));

        $this->assertSame(['DASHBOARD_VIEW'], $unused);
    }
}
