# Hệ Thống Quản Lý Cửa Hàng Giặt Ủi

## Giới thiệu

Dự án này là giao diện quản lý cho hệ thống cửa hàng giặt ủi được xây dựng trên Laravel, theo kiểu dashboard admin hiện đại, sử dụng template Spark Admin đã được tùy chỉnh với tông màu trắng và xanh dương nhạt.

Mục tiêu chính là tạo ra một nền UI thân thiện, chuyên nghiệp, dễ sử dụng cho quản lý và nhân viên trong cửa hàng giặt ủi.

---

## Tính năng UI đã làm

### 1. Giao diện đăng nhập
- Logo máy giặt
- Form đăng nhập với email và mật khẩu
- Toggle ẩn/hiện mật khẩu
- Thiết kế trắng/xanh dương nhạt
- Responsive trên mobile và desktop

### 2. Dashboard admin
- Thẻ thống kê tổng quan
- Tổng đơn hàng
- Doanh thu
- Khách hàng mới
- Đơn chờ xử lý
- Biểu đồ doanh thu và trạng thái đơn hàng
- Bảng đơn hàng gần đây
- Danh sách khách hàng mới
- Khuyến mãi đang áp dụng

### 3. Sidebar quản trị
- Dashboard
- Quản lý đơn hàng
- Quản lý khách hàng
- Quản lý dịch vụ
- Quản lý loại đồ giặt
- Quản lý bảng giá
- Quản lý giao nhận
- Quản lý thanh toán
- Quản lý hóa đơn
- Quản lý khuyến mãi
- Quản lý báo cáo
- Quản lý tài khoản
- Quản lý thông báo

### 4. Giao diện quản lý
- Bảng dữ liệu danh sách
- Nút thao tác xem/sửa/xóa
- Form tìm kiếm và lọc
- Layout card và table chuẩn admin

---

## Thư mục chính

```text
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
│               └── ...
├── public/
│   └── assets/
│       ├── css/
│       │   └── laundry.css
│       ├── images/
│       │   └── icon_maygiat.png
│       └── libs/
├── resources/
│   └── views/
│       ├── auth/
│       │   └── login.blade.php
│       ├── admin/
│       │   ├── dashboard.blade.php
│       │   ├── orders/
│       │   ├── customers/
│       │   └── services/
│       └── layouts/
│           └── app.blade.php
├── routes/
│   └── web.php
├── database/
│   └── seeders/
│       └── DatabaseSeeder.php
├── README.md
└── UI_DOCUMENTATION.md
```

---

## Thông tin đăng nhập demo

### 1. Admin / Quản lý
- Email: `admin@giatui.com`
- Mật khẩu: `admin123`

### 2. Nhân viên
- Email: `staff@giatui.com`
- Mật khẩu: `staff123`

> Các tài khoản này đã được thêm vào seeder để có thể đăng nhập vào hệ thống demo.

---

## Cách chạy dự án

```bash
cd d:\laragon\www\Web_Laravel_QuanLyGiatUi\Laravel_Web_QuanLyCuaHangGiatUi
composer install
php artisan migrate
php artisan db:seed
php artisan serve
```

Sau đó truy cập:

```text
http://127.0.0.1:8000/login
```

---

## Công nghệ sử dụng

- Laravel
- Bootstrap 5
- Bootstrap Icons
- ApexCharts
- HTML/CSS/JS

---

## Ghi chú

Dự án hiện đang là phiên bản UI/admin demo cho hệ thống quản lý cửa hàng giặt ủi, phù hợp để tiếp tục mở rộng thêm module nghiệp vụ thực tế cho quản lý, nhân viên và khách hàng.

---

## Mục tiêu phát triển tiếp theo

- Tích hợp CRUD database thật
- Quản lý đơn hàng, khách hàng, dịch vụ
- Quản lý thanh toán và hóa đơn
- Báo cáo doanh thu theo thời gian
- Tích hợp auth phân quyền theo vai trò
- Phát triển phần app mobile cho khách hàng


