# 🧺 HỆ THỐNG QUẢN LÝ CỬA HÀNG GIẶT ỦI (LAUNDRY STORE MANAGEMENT SYSTEM)

**Sky Laundry** — Hệ thống quản lý cửa hàng giặt ủi toàn diện được xây dựng trên nền tảng **Laravel Framework 13**, cung cấp giải pháp tự động hóa quy trình từ **Tiếp nhận dịch vụ → Tạo đơn hàng → Đặt lịch giao nhận → Thu tiền → Báo cáo thống kê**. Hệ thống hỗ trợ phân quyền **Admin** & **Staff** với giao diện quản trị hiện đại dựa trên **Blade Template**, **Bootstrap 5**, **Tailwind CSS** và **SweetAlert2**.

> Dựa trên dự án **Web_Laravel_QuanLyGiatUi** — quản lý hoạt động cửa hàng giặt ủi từ A-Z.

---

## 🛠️ Công Nghệ Sử Dụng (Tech Stack)

- **Backend Framework:** Laravel Framework `^13.17`
- **Ngôn Ngữ:** PHP `>= 8.3`
- **Frontend / UI:** HTML5, CSS3, JavaScript (jQuery + AJAX), Bootstrap 5, Tailwind CSS v4, FontAwesome / Bootstrap Icons, SweetAlert2, Flatpickr
- **Build Tool:** Vite `^8.0` (ESM module, Tailwind plugin, Bunny "Instrument Sans" font)
- **CSDL:** MySQL / MariaDB (production) — SQLite (dev mặc định)
- **ORM:** Eloquent ORM (hỗ trợ SQLite / MySQL / SQL Server)
- **Export Excel:** PhpSpreadsheet `^5.10`
- **Môi trường:** Laragon / XAMPP / Local Server
- **Code Quality:** Laravel Pint, PHPUnit `^12.5`

---

## 🚀 Các Feature / Module Chính

| # | Module | Mô tả chi tiết |
|---|--------|----------------|
| 1 | **Tổng Quan (Dashboard)** | Thống kê doanh thu, tổng số đơn hàng, khách hàng. Tiện ích **Lịch Giao Nhận Trong Ngày** (hiển thị label tiếng Việt qua accessor) & **Hóa Đơn Chờ Thanh Toán** với nút **thu tiền nhanh** (`dashboard.collect-cash-payment`). |
| 2 | **Quản Lý Dịch Vụ & Danh Mục (Services & Categories)** | CRUD dịch vụ giặt sấy, giặt hấp, giặt chăn mền... Tìm kiếm, phân loại trạng thái, bộ lọc sắp xếp theo giá / ngày tạo. |
| 3 | **Quản Lý Khách Hàng (Customers)** | Quản lý thông tin cá nhân, lịch sử sử dụng dịch vụ, tổng chi tiêu & **cấp bậc loyalty tự động**. |
| 4 | **Quản Lý Đơn Hàng (Orders)** | Tạo đơn hàng đa dịch vụ, tính tiền tự động, theo dõi tiến độ xử lý qua `OrderService::getStatusFlow()` (washing → "Đang giặt", washed → "Đã giặt xong"). |
| 5 | **Lịch Giao Nhận (Deliveries)** | Quản lý lịch lấy đồ tận nơi & giao đồ tận nhà. Accessor `type_label`, `status_label`, `status_badge_class` chuẩn tiếng Việt. |
| 6 | **Đặt Lịch Online (Bookings)** | Tiếp nhận & xử lý yêu cầu đặt lịch hẹn trực tuyến. |
| 7 | **Hóa Đơn & Thanh Toán (Invoices & Payments)** | Tự động xuất hóa đơn từ đơn hàng (`Order::invoice()` dạng `hasOne`). Hỗ trợ **export Excel** (`GET invoices/export` → file `Danh_Sach_Hoa_Don_[YYYY_MM_DD].xlsx`, UTF-8, áp dụng query filters) & xác nhận thanh toán tiền mặt/chuyển khoản nhanh. |
| 8 | **Khuyến Mãi & Voucher (Promotions & Coupons)** | Quản lý mã giảm giá, chương trình ưu đãi theo thời gian, mối quan hệ với Coupon. |

---

## 💻 Hướng Dẫn Cài Đặt & Chạy Cục Bản (Installation Guide)

### 1. Yêu cầu hệ thống

- PHP `>= 8.3`
- Composer `2.x+`
- Node.js `18.x+` & npm (cho frontend assets)
- MySQL `>= 5.7` / MariaDB (production) hoặc SQLite (dev)

### 2. Các bước cài đặt

```bash
# 1. Di chuyển vào thư mục dự án
cd D:\laragon\www\Web_Laravel_QuanLyGiatUi\Laravel_Web_QuanLyCuaHangGiatUi

# 2. Cài đặt các thư viện PHP qua Composer
composer install

# 3. Tạo file cấu hình môi trường
cp .env.example .env

# 4. Tạo Application Key
php artisan key:generate

# 5. Cấu hình Database trong file .env (DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)

# 6. Chạy Migration và Seed dữ liệu mẫu chuẩn Tiếng Việt
php artisan migrate:fresh --seed

# 7. (Tùy chọn) Cài đặt frontend dependencies & build assets
npm install
npm run build

# 8. Xóa toàn bộ cache ứng dụng
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear

# 9. Khởi chạy máy chủ ảo (nếu không dùng Laragon Virtual Host)
php artisan serve
```

> **Truy cập:** `http://localhost:8000` — đăng nhập với tài khoản demo (xem bảng dưới).

---

## 📂 Cấu Trúc Thư Mục Dự Án (Project Structure)

```text
Laravel_Web_QuanLyCuaHangGiatUi/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── LoginController.php
│   │   │   └── Admin/                    # 14 Controller quản lý CRUD từng Module
│   │   │       ├── AccountController.php
│   │   │       ├── BookingController.php
│   │   │       ├── CouponController.php
│   │   │       ├── CustomerController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── DeliveryController.php
│   │   │       ├── GarmentConditionController.php
│   │   │       ├── GarmentController.php
│   │   │       ├── InvoiceController.php
│   │   │       ├── NotificationController.php
│   │   │       ├── OrderController.php
│   │   │       ├── OrderItemController.php
│   │   │       ├── PaymentController.php
│   │   │       ├── PricingController.php
│   │   │       ├── PromotionController.php
│   │   │       ├── ReportController.php
│   │   │       ├── ServiceCategoryController.php
│   │   │       └── ServiceController.php
│   │   ├── Middleware/                   # Xác thực & phân quyền (auth, role:admin)
│   │   ├── Requests/                    # Form Request Validation (chung)
│   │   │   └── Admin/                   # 14 Form Request riêng cho từng Module
│   │   │       ├── AccountRequest.php
│   │   │       ├── BookingRequest.php
│   │   │       ├── CouponRequest.php
│   │   │       ├── CustomerRequest.php
│   │   │       ├── DeliveryRequest.php
│   │   │       ├── GarmentConditionRequest.php
│   │   │       ├── GarmentRequest.php
│   │   │       ├── InvoiceRequest.php
│   │   │       ├── OrderItemRequest.php
│   │   │       ├── OrderRequest.php
│   │   │       ├── PaymentRequest.php
│   │   │       ├── PricingRequest.php
│   │   │       ├── PromotionRequest.php
│   │   │       ├── ServiceCategoryRequest.php
│   │   │       └── ServiceRequest.php
│   ├── Models/                          # 16 Eloquent Model + Accessor Tiếng Việt
│   │   ├── Booking.php, Coupon.php, Customer.php, Delivery.php
│   │   ├── Garment.php, GarmentCondition.php, Invoice.php, Notification.php
│   │   ├── Order.php (hasOne Invoice), OrderItem.php, Payment.php
│   │   ├── Pricing.php, Promotion.php, Service.php, ServiceCategory.php, User.php
│   ├── Policies/                         # Quy tắc phân quyền chi tiết
│   ├── Providers/                       # Service Provider (Auth, App...)
│   ├── Services/                        # 14 Service Layer (logic nghiệp vụ)
│   │   ├── AccountService.php, BookingService.php, CouponService.php
│   │   ├── CustomerService.php, DashboardService.php, DeliveryService.php
│   │   ├── GarmentService.php, InvoiceService.php (find by id & code)
│   │   ├── OrderService.php (getStatusFlow), PaymentService.php
│   │   ├── PricingService.php, PromotionService.php, ReportService.php, ServiceService.php
│   ├── Exports/                          # Export Excel (PhpSpreadsheet)
│   ├── database/
│   │   ├── factories/                   # 16 Model Factories
│   │   ├── migrations/                  # Cấu trúc bảng CSDL
│   │   └── seeders/                     # 17 Seeders (chuẩn Tiếng Việt)
│   ├── routes/
│   │   └── web.php                      # Định tuyến ứng dụng + phân quyền middleware
│   ├── resources/views/admin/           # 15 thư mục view module (Blade)
│   ├── resources/views/auth/            # Trang đăng nhập
│   ├── resources/views/layouts/         # Bố cục chung (app, header, sidebar)
│   ├── resources/css/, resources/js/    # Tailwind + jQuery
│   └── public/assets/                    # CSS/JS/Images libs (Bootstrap, FontAwesome, SweetAlert2, Flatpickr)
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
├── tests/
│   ├── Feature/
│   └── Unit/
├── .env.example
├── composer.json
├── package.json
├── vite.config.js
└── README.md
```

---

## 🔐 Phân Quyền (Authorization)

Hệ thống phân quyền dựa trên cột `role` của bảng `users`:

| Vai Trò | Mô tả | Quyền Truy Cập |
|---------|-------|-----------------|
| **Admin** (`admin`) | Quản trị viên toàn quyền | Toàn bộ module + Reports + Accounts + Promotions + Coupons + Pricings + Notifications + Services + Service Categories + Garments + Garment Conditions |
| **Staff** (`staff`) | Nhân viên vận hành | Orders, Order Items, Customers, Deliveries, Bookings, Payments, Invoices, Dashboard + Profile & Settings |

> Các module `promotions`, `coupons`, `pricings`, `accounts`, `notifications`, `reports`, `services`, `service-categories`, `garments`, `garment-conditions` được bảo vệ bởi middleware `role:admin`.

---

## 👤 Tài Khoản Demo

> Dữ liệu được sinh từ `database/seeders/UserSeeder.php`. Tất cả tài khoản được tạo bởi `php artisan migrate:fresh --seed`.

| Vai Trò | Email | Mật Khẩu | Trạng Thái |
|---------|-------|----------|------------|
| **Quản trị viên (Admin)** | `admin@giatui.com` | `12345678` | Hoạt động |
| **Nhân viên (Staff) 1** | `staff1@giatui.com` | `staff123` | Hoạt động |
| **Nhân viên (Staff) 2** | `staff2@giatui.com` | `staff123` | Hoạt động |
| **Nhân viên (Staff) 3** | `staff3@giatui.com` | `staff123` | Hoạt động |
| **Khách hàng (Customer) mẫu** | `customer1-10@email.com` | `password` | Hoạt động |

> ⚠️ **Khách hàng (Customer)** trong module `Customers` là hồ sơ thông tin khách hàng (bảng `customers`) được quản lý riêng, **không phải tài khoản đăng nhập admin panel**. Ngoài ra, `UserSeeder` còn tạo 10 user mẫu có `role = customer` (email `customer1-10@email.com`, mật khẩu `password`) cho mục đích test — role này **không được phép truy cập** các module được bảo vệ middleware `role:admin`.

---

## ⚙️ Cấu Hình Cơ Sở Dữ Liệu

Dự án hỗ trợ nhiều loại CSDL thông qua biến môi trường trong file `.env`:

```env
# SQLite (mặc định - dùng cho phát triển)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# MySQL / MariaDB
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
```

### Hướng dẫn cấu hình Laragon

1. Mở **Laragon** → chọn **"Start"**.
2. Tạo database mới trong **phpMyAdmin** (nếu dùng MySQL).
3. Cập nhật `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` trong file `.env`.
4. Chạy lệnh seeding:
   ```bash
   php artisan migrate:fresh --seed
   ```
5. Truy cập `http://laravel-quanlygiuoui.test` (hoặc domain tùy chỉnh).

---

## 📋 Quy Tắc Phát Triển (Development Guidelines)

- **Form Request Validation:** Tất cả request đều sử dụng Form Request riêng biệt trong `app/Http/Requests/Admin/`.
- **Service Layer:** Logic nghiệp vụ được tách ra thành Service classes trong `app/Services/`.
- **DB Transactions:** Các thao tác ghi dữ liệu phức tạp (Orders, Payments, Invoices) sử dụng `DB::transaction()`.
- **Eloquent ORM:** Truy vấn CSDL thông qua Model relationships, hỗ trợ SQLite/MySQL/SQL Server.
- **Status Badges:** Tất cả badge trạng thái được chuẩn hóa với màu sắc đồng nhất (success/warning/danger/info).
- **Tiếng Việt hoá toàn diện:** Trạng thái & danh mục hiển thị trên giao diện được ánh xạ tiếng Việt qua Eloquent Accessor (ví dụ: `Delivery::getStatusLabelAttribute()`, `OrderService::getStatusFlow()`).
- **Invoice Export:** `GET /invoices/export` tạo file Excel `Danh_Sách_Hóa_Đơn_[YYYY_MM_DD].xlsx` định dạng UTF-8, áp dụng `request()->query()` filters.
- **InvoiceService::find()** hỗ trợ resolve bởi cả `id` và `code`.
- **Order::invoice()** trả về quan hệ `hasOne(Invoice::class)` (Invoice có `order_id`).

---

## 📝 Ghi Chú & Bảo Trì

- Khi cập nhật mã nguồn giao diện, chạy `php artisan view:clear` để áp dụng thay đổi.
- Khi thay đổi route hoặc config, chạy `php artisan route:clear` & `php artisan config:clear`.
- Để reset dữ liệu về trạng thái mẫu ban đầu: `php artisan migrate:fresh --seed`.
- Debug mode `APP_DEBUG=true` trong môi trường dev.

### Khắc phục khi đăng nhập lỗi "Email hoặc mật khẩu không đúng"

Nếu nhập đúng thông tin tài khoản mẫu nhưng vẫn không đăng nhập được, nguyên nhân thường là **seeders chưa chạy** (bảng `users` rỗng). Kiểm tra và chạy lại:

```bash
# Xác minh có dữ liệu user chưa
php artisan tinker
>>> App\Models\User::count();   // phải trả về > 0

# Nếu = 0, chạy seed dữ liệu
php artisan migrate:fresh --seed

# Sau đó dọn cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

---

> 💡 **Mẹo nhanh:** Chạy `php artisan migrate:fresh --seed` mỗi khi cần reset dữ liệu về trạng thái mẫu ban đầu. File `README.md` này đã được cập nhật tự động dựa trên quét toàn bộ cấu trúc mã nguồn dự án.
