# 🏪 Hệ Thống Quản Lý Cửa Hàng Giặt Ủi

> **Web_Laravel_QuanLyGiatUi** — Hệ thống quản lý cửa hàng giặt ủi chuyên nghiệp dành cho **Admin** & **Staff**, xây dựng trên Laravel 13 Framework.

---

## 🚀 Giới Thiệu Chung

**Web_Laravel_QuanLyGiatUi** là một ứng dụng web quản lý hoạt động của cửa hàng giặt ủi, bao gồm quản lý khách hàng, dịch vụ, đồ đạc giặt, đơn hàng, booking/giao hàng, thanh toán, khuyến mãi, và báo cáo thống kê. Hệ thống hỗ trợ phân quyền **Admin** và **Staff** với giao diện quản trị hiện đại dựa trên **Blade Template**, **Bootstrap 5** và **FontAwesome**.

- **Mục tiêu:** Tối ưu quy trình quản lý cửa hàng giặt ủi từ A-Z (nhập hàng → giặt → giao → thanh toán).
- **Đối tượng:** Quản trị viên (Admin) và nhân viên (Staff) của cửa hàng.

---

## 🛠️ Công Nghệ Sử Dụng

| LAYER | CÔNG NGHỆ | PHIÊN BẢN / LƯU Ý |
|-------|-----------|-------------------|
| **Backend** | Laravel Framework | 13.17+ |
| | PHP | 8.3+ |
| **Frontend** | Blade Template | Laravel native |
| | CSS Framework | Bootstrap 5 |
| | Icons | FontAwesome |
| | JavaScript | jQuery + AJAX |
| **Database** | SQLite | Mặc định (dev) |
| | MySQL / SQL Server | Hỗ trợ qua Eloquent ORM |
| **Dev Tools** | Laravel Pint | Code formatting |
| | Faker PHP | Test data generation |
| | PHPUnit | 12.5+ |

---

## 📂 Cấu Trúc Dự Án

```
Laravel_Web_QuanLyCuaHangGiatUi/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── LoginController.php
│   │   │   ├── Admin/
│   │   │   │   ├── AccountController.php
│   │   │   │   ├── BookingController.php
│   │   │   │   ├── CouponController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── DeliveryController.php
│   │   │   │   ├── GarmentConditionController.php
│   │   │   │   ├── GarmentController.php
│   │   │   │   ├── InvoiceController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── OrderItemController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── PricingController.php
│   │   │   │   ├── PromotionController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── ServiceCategoryController.php
│   │   │   │   └── ServiceController.php
│   │   ├── Middleware/
│   │   └── Requests/
│   │       ├── AccountRequest.php
│   │       ├── BookingRequest.php
│   │       ├── CouponRequest.php
│   │       ├── CustomerRequest.php
│   │       ├── DeliveryRequest.php
│   │       ├── GarmentConditionRequest.php
│   │       ├── GarmentRequest.php
│   │       ├── InvoiceRequest.php
│   │       ├── OrderItemRequest.php
│   │       ├── OrderRequest.php
│   │       ├── PaymentRequest.php
│   │       ├── PricingRequest.php
│   │       ├── PromotionRequest.php
│   │       ├── ServiceCategoryRequest.php
│   │       └── ServiceRequest.php
│   ├── Models/
│   │   ├── Booking.php
│   │   ├── Coupon.php
│   │   ├── Customer.php
│   │   ├── Delivery.php
│   │   ├── Garment.php
│   │   ├── GarmentCondition.php
│   │   ├── Invoice.php
│   │   ├── Notification.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Payment.php
│   │   ├── Pricing.php
│   │   ├── Promotion.php
│   │   ├── Service.php
│   │   ├── ServiceCategory.php
│   │   └── User.php
│   └── Services/
│       ├── AccountService.php
│       ├── BookingService.php
│       ├── CouponService.php
│       ├── CustomerService.php
│       ├── DashboardService.php
│       ├── DeliveryService.php
│       ├── GarmentService.php
│       ├── OrderService.php
│       ├── PaymentService.php
│       ├── PricingService.php
│       ├── PromotionService.php
│       ├── ReportService.php
│       └── ServiceService.php
├── database/
│   ├── factories/          (16 factories)
│   │   ├── BookingFactory.php
│   │   ├── CouponFactory.php
│   │   ├── CustomerFactory.php
│   │   ├── DeliveryFactory.php
│   │   ├── GarmentConditionFactory.php
│   │   ├── GarmentFactory.php
│   │   ├── InvoiceFactory.php
│   │   ├── NotificationFactory.php
│   │   ├── OrderFactory.php
│   │   ├── OrderItemFactory.php
│   │   ├── PaymentFactory.php
│   │   ├── PricingFactory.php
│   │   ├── PromotionFactory.php
│   │   ├── ServiceCategoryFactory.php
│   │   ├── ServiceFactory.php
│   │   └── UserFactory.php
│   └── seeders/            (15 seeders)
│       ├── BookingSeeder.php
│       ├── CouponSeeder.php
│       ├── CustomerSeeder.php
│       ├── DatabaseSeeder.php
│       ├── DeliverySeeder.php
│       ├── GarmentConditionSeeder.php
│       ├── GarmentSeeder.php
│       ├── InvoiceSeeder.php
│       ├── NotificationSeeder.php
│       ├── OrderSeeder.php
│       ├── PaymentSeeder.php
│       ├── PricingSeeder.php
│       ├── PromotionSeeder.php
│       ├── ServiceSeeder.php
│       └── UserSeeder.php
├── resources/
│   ├── views/
│   │   ├── admin/          (15 module view folders)
│   │   │   ├── accounts/
│   │   │   ├── bookings/
│   │   │   ├── coupons/
│   │   │   ├── customers/
│   │   │   ├── deliveries/
│   │   │   ├── garment-conditions/
│   │   │   ├── garments/
│   │   │   ├── invoices/
│   │   │   ├── notifications/
│   │   │   ├── order-items/
│   │   │   ├── orders/
│   │   │   ├── payments/
│   │   │   ├── pricings/
│   │   │   ├── promotions/
│   │   │   ├── reports/
│   │   │   ├── service-categories/
│   │   │   ├── services/
│   │   │   └── partials/
│   │   ├── auth/
│   │   │   └── login.blade.php
│   │   └── layouts/
│   │       ├── app.blade.php
│   │       ├── header.blade.php
│   │       └── sidebar.blade.php
│   └── css/, js/
└── routes/
    └── web.php
```

---

## ✅ Trạng Thái Các Module

| STT | MODULE | MODEL | CONTROLLER | ROUTES | TRẠNG THÁI | TÍNH NĂNG CHÍNH |
|-----|--------|-------|------------|--------|------------|-----------------|
| 1 | **Users / Accounts** | `User` | `AccountController` | 6 | ✅ Hoàn thành | CRUD, Toggle Status, Reset Password, Form Request Validation |
| 2 | **Customers** | `Customer` | `CustomerController` | 7 | ✅ Hoàn thành | CRUD, Loyalty Level tự động, DB Transactions |
| 3 | **Services** | `Service` | `ServiceController` | 8 | ✅ Hoàn thành | CRUD, Toggle Status, Processing Time, Form Request Validation |
| 4 | **Service Categories** | `ServiceCategory` | `ServiceCategoryController` | 7 | ✅ Hoàn thành | CRUD, Toggle Status, Filter/Search |
| 5 | **Garments** | `Garment` | `GarmentController` | 7 | ✅ Hoàn thành | CRUD, Category dropdown, Condition pricing, Toggle Status |
| 6 | **Garment Conditions** | `GarmentCondition` | `GarmentConditionController` | 7 | ✅ Hoàn thành | CRUD, Pricing by condition, Filter |
| 7 | **Orders** | `Order` | `OrderController` | 7 | ✅ Hoàn thành | CRUD, Status badge, Order Items nested |
| 8 | **Order Items** | `OrderItem` | `OrderItemController` | 7 | ✅ Hoàn thành | CRUD, Quantity/Price tracking |
| 9 | **Bookings / Delivery** | `Booking` / `Delivery` | `BookingController` / `DeliveryController` | 7 + 7 | ✅ Hoàn thành | CRUD, Booking schedule, Delivery tracking |
| 10 | **Payments** | `Payment` | `PaymentController` | 7 | ✅ Hoàn thành | CRUD, Payment method, Status badge |
| 11 | **Invoices** | `Invoice` | `InvoiceController` | 7 | ✅ Hoàn thành | CRUD, Invoice PDF, Payment status |
| 12 | **Promotions** | `Promotion` | `PromotionController` | 7 | ✅ Hoàn thành | CRUD, Coupon relationship, Pagination |
| 13 | **Coupons** | `Coupon` | `CouponController` | 8 | ✅ Hoàn thành | CRUD, Toggle Status, Discount type/value |
| 14 | **Pricings** | `Pricing` | `PricingController` | 7 | ✅ Hoàn thành | CRUD, Service + Condition pricing matrix |
| 15 | **Reports** | — | `ReportController` | 4 | ✅ Hoàn thành | Dashboard, Revenue, Orders, Customers stats |
| 16 | **Notifications** | `Notification` | `NotificationController` | 7 | ✅ Hoàn thành | CRUD, Admin only, Status badge |

### 🔐 Phân Quyền

| Vai Trò | Mô Tả | Quyền Truy Cập |
|---------|-------|-----------------|
| **Admin** (`admin`) | Quản trị viên toàn quyền | Toàn bộ module + Reports + Accounts + Promotions + Coupons + Pricings + Notifications |
| **Staff** (`staff`) | Nhân viên vận hành | Orders, Order Items, Customers, Services, Service Categories, Garments, Garments Conditions, Bookings, Deliveries, Payments, Invoices |

> **Lưu ý:** Các module `promotions`, `coupons`, `pricings`, `accounts`, `notifications`, `reports` được bảo vệ bởi middleware `role:admin`.

---

## 👤 Tài Khoản Demo

> Dữ liệu được đọc trực tiếp từ `database/seeders/UserSeeder.php`. Tất cả tài khoản mẫu được tạo bởi `php artisan migrate:fresh --seed`.

| Vai Trò | Email | Mật Khẩu | Trạng Thái |
|---------|-------|----------|------------|
| **Quản trị viên (Admin)** | `admin@giatui.com` | `12345678` | Hoạt động |
| **Nhân viên (Staff) 1** | `staff1@giatui.com` | `staff123` | Hoạt động |
| **Nhân viên (Staff) 2** | `staff2@giatui.com` | `staff123` | Hoạt động |
| **Nhân viên (Staff) 3** | `staff3@giatui.com` | `staff123` | Hoạt động |

> ⚠️ **Khách hàng (Customer)** chỉ là hồ sơ thông tin khách hàng trong hệ thống, **không có tài khoản đăng nhập**. Họ được quản lý thông qua module `Customers`.

---

## ⚙️ Hướng Dẫn Cài Đặt & Chạy Dự Án

### Yêu Cầu Hệ Thống

- **PHP** 8.3+
- **Composer** 2.x+
- **Node.js** 18.x+ & **npm** (cho frontend assets)
- **SQLite** (mặc định) hoặc **MySQL 8.0+**

### Các Bước Cài Đặt

1. **Clone repository:**
   ```bash
   git clone <repository_url>
   cd Laravel_Web_QuanLyCuaHangGiatUi
   ```

2. **Cài đặt Composer dependencies:**
   ```bash
   composer install
   ```

3. **Tạo file môi trường `.env`:**
   ```bash
   cp .env.example .env
   ```

4. **Tạo application key:**
   ```bash
   php artisan key:generate
   ```

5. **Chạy migration và seeding dữ liệu mẫu:**
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **(Tùy chọn) Cài đặt frontend dependencies:**
   ```bash
   npm install
   npm run build
   ```

7. **Khởi chạy server phát triển:**
   ```bash
   php artisan serve
   ```

8. **Truy cập ứng dụng:**
   ```
   http://localhost:8000
   ```
   - Đăng nhập với tài khoản demo ở trên.

---

## 💡 Ghi Chú & Quy Tắc Bảo Trì

### Cấu Hình Cơ Sở Dữ Liệu

Dự án hỗ trợ nhiều loại CSDL thông qua biến môi trường trong file `.env`:

```env
# SQLite (mặc định - dùng cho phát triển)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

### Hướng Dẫn Cấu Hình Laragon

1. Mở **Laragon** → chọn **"Start"**.
2. Tạo database mới trong **phpMyAdmin** (nếu dùng MySQL).
3. Cập nhật thông tin `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` trong file `.env`.
4. Chạy lệnh seeding:
   ```bash
   php artisan migrate:fresh --seed
   ```
5. Truy cập `http://laravel-quanlygiuoui.test` (hoặc domain tùy chỉnh).

### Các Quy Tắc Phát Triển

- **Form Request Validation:** Tất cả các request đều sử dụng Form Request riêng biệt trong `app/Http/Requests/`.
- **Service Layer:** Logic nghiệp vụ được tách ra thành Service classes trong `app/Services/`.
- **DB Transactions:** Các thao tác ghi dữ liệu phức tạp (Orders, Payments, Invoices) sử dụng `DB::transaction()`.
- **Eloquent ORM:** Truy vấn CSDL thông qua Model relationships, hỗ trợ SQLite/MySQL/SQL Server.
- **Status Badges:** Tất cả badge trạng thái được chuẩn hóa với màu sắc đồng nhất (success/warning/danger/info).

---

## 📞 Thông Tin Liên Hệ

- **Project:** Web_Laravel_QuanLyGiatUi
- **Framework:** Laravel 13 / PHP 8.3+
- **Developer:** Senior Lead Developer Team

---

> 💡 **Mẹo nhanh:** Chạy `php artisan migrate:fresh --seed` mỗi khi cần reset dữ liệu về trạng thái mẫu ban đầu.
