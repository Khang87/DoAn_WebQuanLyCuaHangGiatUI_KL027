<?php

namespace App\Support;

/**
 * Danh mục mã quyền chuẩn của hệ thống.
 *
 * Đây là nguồn sự thật duy nhất (single source of truth) dùng cho cả:
 *   - Seeder tạo bảng `permissions`
 *   - Gate::define() khi khởi động ứng dụng (không query DB lúc boot)
 *   - Validate ma trận phân quyền trên giao diện
 */
final class PermissionRegistry
{
    /**
     * Danh sách quyền theo từng module: group => [code => tên hiển thị].
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Đơn hàng' => [
                'orders.view' => 'Xem đơn hàng',
                'orders.create' => 'Thêm đơn hàng',
                'orders.edit' => 'Sửa đơn hàng chưa thanh toán',
                'orders.delete' => 'Xóa đơn hàng chưa thanh toán',
                'orders.update_status' => 'Đổi trạng thái đơn hàng',
                'orders.edit_completed' => 'Sửa đơn đã thanh toán',
                'orders.delete_completed' => 'Xóa đơn đã thanh toán',
                'orders.refund' => 'Hoàn tiền đơn hàng',
            ],
            'Hóa đơn' => [
                'invoices.view' => 'Xem hóa đơn',
                'invoices.create' => 'Thêm hóa đơn',
                'invoices.edit' => 'Sửa hóa đơn chưa thanh toán',
                'invoices.delete' => 'Xóa hóa đơn chưa thanh toán',
                'invoices.update_status' => 'Đổi trạng thái hóa đơn',
                'invoices.edit_paid' => 'Sửa hóa đơn đã thanh toán',
                'invoices.delete_paid' => 'Xóa hóa đơn đã thanh toán',
            ],
            'Thanh toán' => [
                'payments.view' => 'Xem thanh toán',
                'payments.create' => 'Ghi nhận thanh toán',
                'payments.edit' => 'Sửa khoản thu',
                'payments.delete' => 'Xóa khoản thu',
                'payments.edit_paid' => 'Sửa khoản thu đã thanh toán',
                'payments.delete_paid' => 'Xóa khoản thu đã thanh toán',
                'payments.refund' => 'Hoàn tiền khoản thu',
            ],
            'Dịch vụ' => [
                'services.view' => 'Xem dịch vụ',
                'services.create' => 'Thêm dịch vụ',
                'services.edit' => 'Sửa dịch vụ',
                'services.delete' => 'Xóa dịch vụ',
                'service_categories.view' => 'Xem danh mục dịch vụ',
                'service_categories.create' => 'Thêm danh mục dịch vụ',
                'service_categories.edit' => 'Sửa danh mục dịch vụ',
                'service_categories.delete' => 'Xóa danh mục dịch vụ',
            ],
            'Khách hàng' => [
                'customers.view' => 'Xem khách hàng',
                'customers.create' => 'Thêm khách hàng',
                'customers.edit' => 'Sửa khách hàng',
                'customers.delete' => 'Xóa khách hàng',
            ],
            'Giao nhận' => [
                'deliveries.view' => 'Xem giao nhận',
                'deliveries.create' => 'Thêm lịch giao nhận',
                'deliveries.edit' => 'Sửa giao nhận',
                'deliveries.delete' => 'Xóa giao nhận',
                'bookings.view' => 'Xem lịch hẹn',
                'bookings.edit' => 'Sửa lịch hẹn',
                'bookings.delete' => 'Xóa lịch hẹn',
                'bookings.confirm' => 'Xác nhận lịch hẹn',
            ],
            'Đồ giặt & Giá' => [
                'garments.view' => 'Xem loại đồ giặt',
                'garments.create' => 'Thêm loại đồ giặt',
                'garments.edit' => 'Sửa loại đồ giặt',
                'garments.delete' => 'Xóa loại đồ giặt',
                'garment_conditions.view' => 'Xem điều kiện đồ giặt',
                'garment_conditions.create' => 'Thêm điều kiện đồ giặt',
                'garment_conditions.edit' => 'Sửa điều kiện đồ giặt',
                'garment_conditions.delete' => 'Xóa điều kiện đồ giặt',
                'pricings.view' => 'Xem bảng giá',
                'pricings.create' => 'Thêm bảng giá',
                'pricings.edit' => 'Sửa bảng giá',
                'pricings.delete' => 'Xóa bảng giá',
            ],
            'Khuyến mãi' => [
                'promotions.view' => 'Xem chương trình khuyến mãi',
                'promotions.create' => 'Thêm chương trình khuyến mãi',
                'promotions.edit' => 'Sửa chương trình khuyến mãi',
                'promotions.delete' => 'Xóa chương trình khuyến mãi',
                'coupons.view' => 'Xem mã giảm giá',
                'coupons.create' => 'Thêm mã giảm giá',
                'coupons.edit' => 'Sửa mã giảm giá',
                'coupons.delete' => 'Xóa mã giảm giá',
            ],
            'Đánh giá & Thông báo' => [
                'reviews.view' => 'Xem đánh giá',
                'reviews.respond' => 'Trả lời đánh giá',
                'reviews.toggle' => 'Ẩn/hiện đánh giá',
                'notifications.view' => 'Xem thông báo',
                'notifications.create' => 'Thêm thông báo',
                'notifications.edit' => 'Sửa thông báo',
                'notifications.delete' => 'Xóa thông báo',
            ],
            'Báo cáo' => [
                'reports.view' => 'Xem báo cáo',
                'reports.revenue' => 'Xem báo cáo doanh thu',
            ],
            'Tài khoản & Phân quyền' => [
                'accounts.view' => 'Xem tài khoản',
                'accounts.create' => 'Thêm tài khoản',
                'accounts.edit' => 'Sửa tài khoản',
                'accounts.delete' => 'Xóa tài khoản',
                'accounts.reset_password' => 'Đặt lại mật khẩu',
                'settings.view' => 'Xem cấu hình hệ thống',
                'roles.manage' => 'Quản lý vai trò & quyền hạn',
            ],
        ];
    }

    /**
     * Toàn bộ mã quyền dạng phẳng.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_values(array_unique(array_merge(
            ...array_values(array_map('array_keys', static::groups()))
        )));
    }

    public static function isValidCode(string $code): bool
    {
        return in_array($code, static::codes(), true);
    }
}
