# Hệ Thống Quản Lý Cửa Hàng Giặt Ủi - UI Documentation

## 📋 Tổng Quan Dự Án

Đây là giao diện quản lý cho hệ thống cửa hàng giặt ủi, được xây dựng trên **Laravel Framework** với template **Spark Admin** đã được tùy chỉnh với màu sắc:

- **Màu nền:** Trắng (#FFFFFF)
- **Màu chủ đạo:** Xanh dương nhạt (#60A5FA)

---

## 🎨 Những Gì Đã Được Thực Hiện

### 1. Assets & Styling

#### ✅ Copy Assets
- Copy toàn bộ assets từ template Spark Admin vào `public/assets/`
- Bao gồm: CSS, JS, Images, Libraries (Bootstrap, ApexCharts, Flatpickr, Bootstrap Icons)

#### ✅ Custom CSS
- Tạo file `public/assets/css/laundry.css` với theme tùy chỉnh:
  - Màu sidebar: Gradient xanh dương đậm (#1E3A5F → #1E40AF)
  - Màu primary: Xanh dương nhạt (#60A5FA)
  - Màu background: Trắng (#FFFFFF) và xám nhạt (#F8FAFC)
  - Border radius, shadows, typography theo chuẩn design system

#### ✅ Icon Máy Giặt
- Copy icon `icon_maygiat.png` vào `public/assets/images/`
- Sử dụng cho logo trang Login

---

### 2. Layout & Templates

#### ✅ Layout Chính (`resources/views/layouts/app.blade.php`)
- **Sidebar** với các menu:
  - **Menu Chính:** Dashboard
  - **Quản Lý:** Đơn Hàng, Khách Hàng, Dịch Vụ, Loại Đồ Giặt, Bảng Giá
  - **Giao Nhận & Thanh Toán:** Giao Nhận, Thanh Toán, Hóa Đơn
  - **Khuyến Mãi & Báo Cáo:** Khuyến Mãi, Báo Cáo
  - **Hệ Thống:** Tài Khoản, Thông Báo
- **Top Navbar** với:
  - Toggle sidebar (responsive)
  - Tìm kiếm
  - Thông báo
  - Menu người dùng
- **Profile sidebar** hiển thị thông tin user

---

### 3. Trang Đăng Nhập (`resources/views/auth/login.blade.php`)

#### ✅ Giao diện Login
- Logo máy giặt với fallback icon Bootstrap
- Form đăng nhập với:
  - Input Email
  - Input Password (có toggle hiện/ẩn)
  - Checkbox "Ghi nhớ đăng nhập"
  - Link "Quên mật khẩu"
- Hiển thị lỗi validation
- Responsive design
- Background gradient xanh dương nhạt

---

### 4. Trang Dashboard (`resources/views/admin/dashboard.blade.php`)

#### ✅ Thống kê
- **4 Stat Cards:**
  - Tổng đơn hàng (156)
  - Doanh thu (45.2M VNĐ)
  - Khách hàng mới (23)
  - Đơn chờ xử lý (12)

#### ✅ Biểu đồ
- **Biểu đồ doanh thu** (ApexCharts - Area Chart):
  - Hiển thị theo tháng
  - Gradient fill màu xanh dương
  - Tùy chọn lọc theo thời gian

- **Biểu đồ trạng thái đơn hàng** (ApexCharts - Donut Chart):
  - Chờ xử lý
  - Đang xử lý
  - Hoàn thành
  - Đã hủy

#### ✅ Danh sách
- **Đơn hàng gần đây:**
  - Mã đơn, Khách hàng, Dịch vụ, Tổng tiền, Trạng thái
  - Nút xem, duyệt, in

- **Khách hàng mới:**
  - Avatar, Tên, Email, Badge (Mới/VIP)

- **Khuyến mãi đang áp dụng:**
  - Tên chương trình, Hạn sử dụng

---

### 5. Controllers

#### ✅ Đã tạo 12 Controllers

| Controller | Chức năng |
|------------|-----------|
| `DashboardController` | Trang dashboard chính |
| `LoginController` | Xử lý đăng nhập/đăng xuất |
| `OrderController` | Quản lý đơn hàng (CRUD) |
| `CustomerController` | Quản lý khách hàng (CRUD) |
| `ServiceController` | Quản lý dịch vụ (CRUD) |
| `GarmentController` | Quản lý loại đồ giặt (CRUD) |
| `PricingController` | Quản lý bảng giá (CRUD) |
| `DeliveryController` | Quản lý giao nhận (CRUD) |
| `PaymentController` | Quản lý thanh toán (CRUD) |
| `InvoiceController` | Quản lý hóa đơn (CRUD) |
| `PromotionController` | Quản lý khuyến mãi (CRUD) |
| `ReportController` | Báo cáo thống kê |
| `AccountController` | Quản lý tài khoản (CRUD) |
| `NotificationController` | Quản lý thông báo (CRUD) |

---

### 6. Routes (`routes/web.php`)

#### ✅ Authentication Routes
```
GET  /login          → Hiển thị form đăng nhập
POST /login          → Xử lý đăng nhập
POST /logout         → Đăng xuất
```

#### ✅ Protected Routes (Yêu cầu đăng nhập)
```
GET  /dashboard      → Dashboard

Resource Routes (CRUD):
- /orders            → Quản lý đơn hàng
- /customers         → Quản lý khách hàng
- /services          → Quản lý dịch vụ
- /garments          → Quản lý loại đồ giặt
- /pricings          → Quản lý bảng giá
- /deliveries        → Quản lý giao nhận
- /payments          → Quản lý thanh toán
- /invoices          → Quản lý hóa đơn
- /promotions        → Quản lý khuyến mãi
- /accounts          → Quản lý tài khoản
- /notifications     → Quản lý thông báo

Report Routes:
- /reports           → Trang báo cáo chính
- /reports/revenue   → Báo cáo doanh thu
- /reports/orders    → Báo cáo đơn hàng
- /reports/customers → Báo cáo khách hàng
```

---

### 7. Views Đã Tạo

#### ✅ Cấu trúc thư mục views
```
resources/views/
├── layouts/
│   └── app.blade.php              # Layout chính
├── auth/
│   └── login.blade.php            # Trang đăng nhập
└── admin/
    ├── dashboard.blade.php        # Dashboard
    ├── orders/
    │   └── index.blade.php        # Danh sách đơn hàng
    ├── customers/
    │   └── index.blade.php        # Danh sách khách hàng
    └── services/
        └── index.blade.php        # Danh sách dịch vụ
```

---

## 🔐 Thông Tin Đăng Nhập

### Tài Khoản Demo

#### 👔 Quản Lý (Admin)
```
Email:    admin@giatui.com
Password: admin123
```

**Quyền hạn:**
- Truy cập toàn bộ hệ thống
- Quản lý nhân viên và tài khoản
- Xem báo cáo và thống kê
- Quản lý dịch vụ và bảng giá
- Quản lý khuyến mãi

---

#### 👨💼 Nhân Viên (Staff)
```
Email:    staff@giatui.com
Password: staff123
```

**Quyền hạn:**
- Tiếp nhận và quản lý đơn hàng
- Quản lý khách hàng
- Cập nhật trạng thái đơn
- Hỗ trợ giao nhận
- Thanh toán

---

## 🚀 Cách Chạy Ứng Dụng

### 1. Cài đặt dependencies
```bash
cd Laravel_Web_QuanLyCuaHangGiatUi
composer install
npm install
```

### 2. Cấu hình môi trường
```bash
# Copy file .env
copy .env.example .env

# Tạo application key
php artisan key:generate
```

### 3. Cấu hình database
Chỉnh sửa file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laundry_shop
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Chạy migrations và seeders
```bash
php artisan migrate
php artisan db:seed
```

### 5. Chạy ứng dụng
```bash
php artisan serve
```

Truy cập: **http://localhost:8000**

---

## 📁 Cấu Trúc Thư Mục Quan Trọng

```
Laravel_Web_QuanLyCuaHangGiatUi/
├── app/
│   └── Http/
│       └── Controllers/
│           ├── Auth/
│           │   └── LoginController.php
│           └── Admin/
│               ├── DashboardController.php
│               ├── OrderController.php
│               ├── CustomerController.php
│               ├── ServiceController.php
│               └── ... (các controller khác)
│
├── public/
│   └── assets/
│       ├── css/
│       │   └── laundry.css        # CSS tùy chỉnh
│       ├── images/
│       │   └── icon_maygiat.png   # Icon máy giặt
│       ├── js/
│       └── libs/                  # Libraries
│
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php
│       ├── auth/
│       │   └── login.blade.php
│       └── admin/
│           ├── dashboard.blade.php
│           ├── orders/
│           ├── customers/
│           └── services/
│
└── routes/
    └── web.php
```

---

## 🎯 Tính Năng Theo Vai Trò

### Quản Lý
- [x] Dashboard với thống kê tổng quan
- [x] Quản lý đơn hàng (xem, duyệt, hủy)
- [x] Quản lý khách hàng
- [x] Quản lý dịch vụ và bảng giá
- [x] Quản lý khuyến mãi
- [x] Quản lý nhân viên/tài khoản
- [x] Xem báo cáo doanh thu
- [x] Quản lý giao nhận
- [x] Theo dõi thanh toán

### Nhân Viên
- [x] Dashboard cơ bản
- [x] Tạo và quản lý đơn hàng
- [x] Quản lý khách hàng
- [x] Cập nhật trạng thái đơn
- [x] Hỗ trợ giao nhận
- [x] Thanh toán

---

## 🛠️ Công Nghệ Sử Dụng

| Công nghệ | Phiên bản | Mục đích |
|-----------|-----------|----------|
| Laravel | 11.x | PHP Framework |
| Bootstrap | 5.3 | CSS Framework |
| Bootstrap Icons | 1.x | Icons |
| ApexCharts | 3.x | Biểu đồ |
| Flatpickr | 4.x | Date picker |
| Plus Jakarta Sans | - | Font chữ |

---

## 📝 Ghi Chú

### Màu Sắc Chính
```css
--primary-blue: #60A5FA;        /* Xanh dương nhạt - Primary */
--primary-blue-hover: #3B82F6;  /* Xanh dương đậm - Hover */
--primary-blue-light: #DBEAFE;  /* Xanh dương rất nhạt - Background */
--sidebar-bg: #1E3A5F;          /* Sidebar gradient start */
```

### Responsive Breakpoints
- **Mobile:** < 576px
- **Tablet:** 576px - 991px
- **Desktop:** ≥ 992px

---

## 📧 Liên Hệ

Mọi thắc mắc xin liên hệ:
- **Email:** admin@giatui.com
- **Project:** Đồ án tốt nghiệp - Quản lý cửa hàng giặt ủi

---

**© 2024 Giặt Ủi Pro - Hệ thống quản lý cửa hàng giặt ủi**
