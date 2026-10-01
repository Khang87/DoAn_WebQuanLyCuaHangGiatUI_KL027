<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Thứ tự seed theo phụ thuộc khóa ngoại:
 *
 *   VaiTro/Quyen -> NhanVien/KhachHang -> TaiKhoan (gắn NhanVienID/KhachHangID)
 *   -> danh mục dịch vụ + bảng giá -> đơn hàng -> hoá đơn/thanh toán/giao nhận
 *   -> đặt lịch, thông báo, đánh giá.
 *
 * Các seeder không được gọi ở đây (CategorySeeder, ServiceCategorySeeder,
 * CouponSeeder) vẫn dùng các bảng legacy chưa có trong
 * schema hiện tại nên không được nạp vào Supabase.
 * GarmentSeeder chỉ còn ghi vào bảng schema-backed `LoaiDoGiat`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            NhanVienSeeder::class,
            CustomerSeeder::class,
            RoleAndPermissionSeeder::class,
            ServiceSeeder::class,
            UserSeeder::class,
            PromotionSeeder::class,
            PricingSeeder::class,
            OrderSeeder::class,
            InvoiceSeeder::class,
            PaymentSeeder::class,
            DeliverySeeder::class,
            BookingSeeder::class,
            NotificationSeeder::class,
            ReviewSeeder::class,
        ]);
    }
}
