# 🧺 Hệ Thống Quản Lý Cửa Hàng Giặt Ủi (Laundry Management System)

> **Phiên bản v2.0** — Hệ thống quản lý toàn diện dịch vụ giặt ủi kết hợp Web Admin Control Panel và Restful API phục vụ ứng dụng Mobile (Flutter). Tích hợp Phân quyền động (Dynamic RBAC), Khóa giao dịch tài chính, Tự động hóa quy trình nghiệp vụ và Chuẩn hóa UI/UX.

---

## 📖 1. Giới Thiệu Dự Án

Hệ thống Quản lý Cửa hàng Giặt ủi được phát triển nhằm tối ưu hóa toàn bộ quy trình vận hành dịch vụ giặt ủi từ khâu Đặt lịch (Booking) của khách hàng, Tiếp nhận đồ, Xử lý giặt sấy, Thanh toán, Quản lý khuyến mãi, Báo cáo thống kê đến Chăm sóc khách hàng.

### 🌟 Tính năng nổi bật chính:
- **Quản lý đa kênh**: Kết nối liền mạch giữa Web Quản trị (Admin/Manager/Staff) và App Di động (Khách hàng).
- **Phân quyền nâng cao (Dynamic RBAC)**: Phân cấp rõ ràng giữa **Chủ cửa hàng (Admin)**, **Quản lý (Manager)**, **Nhân viên (Staff)** và **Khách hàng (Customer)**.
- **Khóa an toàn dữ liệu tài chính**: Đơn hàng/Hóa đơn đã thanh toán bị khóa cứng đối với Quản lý & Nhân viên để chống gian lận, chỉ duy nhất Chủ cửa hàng được quyền điều chỉnh ngoại lệ.
- **Tự động hóa luồng làm việc**: Tự động sinh Đơn hàng (`Order`) ngay khi duyệt Lịch đặt (`Booking`).
- **Danh mục dịch vụ động**: Cho phép cấu hình Dịch vụ, Loại đồ giặt và Bảng giá linh hoạt trực tiếp từ giao diện quản trị.

---

## 👑 2. Sơ Đồ Phân Quyền Hệ Thống (RBAC Matrix)

| Chức năng / Module | Admin (Chủ cửa hàng) | Manager (Quản lý) | Staff (Nhân viên) | Customer (Khách hàng) |
| :--- | :---: | :---: | :---: | :---: |
| Quản lý Cấu hình System / RBAC / Roles | 🟢 Full | 🔴 Khóa | 🔴 Khóa | 🔴 Khóa |
| Xem Dashboard & Báo cáo Doanh thu | 🟢 Full | 🟢 Full | 🟡 Giới hạn | 🔴 Khóa |
| Sửa / Xóa Đơn hàng & Hóa đơn **ĐÃ THANH TOÁN** | 🟢 **Quyền duy nhất** | 🔴 Khóa | 🔴 Khóa | 🔴 Khóa |
| Xem / Thêm / Sửa Đơn hàng **CHƯA THANH TOÁN** | 🟢 Full | 🟢 Full | 🟢 Full | 🔴 Khóa |
| Quản lý Lịch đặt (Booking) | 🟢 Full | 🟢 Full | 🟢 Full | 🟡 Tạo / Xem lịch cá nhân |
| Quản lý Dịch vụ / Loại đồ / Khuyến mãi | 🟢 Full | 🟢 Full | 🟢 Chỉ xem | 🟢 Chỉ xem |
| Quản lý Tài khoản / Khách hàng | 🟢 Full | 🟢 Full | 🟡 Xem danh sách | 🟡 Sửa hồ sơ cá nhân |

---

## 📦 3. Các Module Chức Năng Chính

1. **Dashboard & Thống Kê**:
   - Thẻ biểu đồ doanh thu theo ngày/tháng/năm, top dịch vụ đặt nhiều nhất, số lượng đơn hàng theo trạng thái.
   - Lối tắt truy cập nhanh trên thanh Header.
2. **Quản Lý Đặt Lịch (Bookings)**:
   - Tiếp nhận yêu cầu giặt từ App Khách hàng. Duyệt lịch đặt $\rightarrow$ Tự động sinh đơn hàng tương ứng.
3. **Quản Lý Đơn Hàng (Orders)**:
   - Quản lý vòng đời đơn giặt: *Mới tạo $\rightarrow$ Đang xử lý $\rightarrow$ Đang giặt/sấy $\rightarrow$ Chờ giao $\rightarrow$ Đã thanh toán / Hoàn thành*.
   - Khóa nút Sửa/Xóa khi đơn hàng chuyển sang trạng thái **Đã thanh toán** đối với `manager` và `staff`.
4. **Quản Lý Dịch Vụ & Bảng Giá (Services & Prices)**:
   - Cấu hình Loại dịch vụ (Giặt sấy, Giặt hấp, Giặt rèm/chăn mền...), Loại đồ giặt và Đơn giá theo Kg hoặc Chiếc.
5. **Quản Lý Mã Giảm Giá (Vouchers/Promotions)**:
   - Tạo mã giảm giá theo %, thời gian hiệu lực và số lượng phát hành.
6. **Quản Lý Thông Báo (Notifications)**:
   - Gửi thông báo tự động khi thay đổi trạng thái đơn hàng. Phân biệt trực quan thông báo chưa đọc (in đậm) và đã đọc.
7. **Hệ Thống API RESTful**:
   - Cung cấp API xác thực (Sanctum), lấy danh sách dịch vụ, đặt lịch, tra cứu đơn hàng, nhận thông báo cho ứng dụng Flutter.

---

## 🔑 4. Tài Khoản Demo Hệ Thống

Sau khi chạy Seeder, hệ thống sẽ tự động khởi tạo các tài khoản kiểm thử đại diện cho từng vai trò:

| Vai trò (Role) | Tài khoản (Email) | Mật khẩu | Mục đích kiểm thử |
| :--- | :--- | :--- | :--- |
| **Admin (Chủ cửa hàng)** | `admin@gmail.com` | `123456` | Toàn quyền, test xử lý ngoại lệ đơn đã thanh toán. |
| **Manager (Quản lý)** | `manager@gmail.com` | `123456` | Test vận hành cửa hàng, kiểm tra bị khóa đơn đã thanh toán. |
| **Manager (Quản lý)** | `quanly@gmail.com` | `123456` | Tài khoản quản lý phụ trợ, cùng quyền với manager trên. |
| **Staff (Nhân viên)** | `staff@gmail.com` | `123456` | Test xử lý đơn hàng/tiếp nhận đồ hàng ngày. |
| **Staff (Nhân viên)** | `nhanvien@gmail.com` | `123456` | Tài khoản nhân viên phụ trợ (role cũ 'employee' tự động ánh xạ về staff). |
| **Staff (Nhân viên 1/2/3)** | `staff1@giatui.com` / `staff2@giatui.com` / `staff3@giatui.com` | `123456` | Các nhân viên mẫu để test phân công đơn hàng. |
| **Customer (Khách hàng)** | `customer1@email.com` (đến `customer10@email.com`) | `password` | Test giao diện khách hàng / Đặt lịch online. |

> 💡 **Lưu ý**: Tất cả tài khoản trên được tạo bởi `UserSeeder.php`. Chạy `php artisan migrate:fresh --seed` để khởi tạo lại toàn bộ dữ liệu mẫu.

---

## 📁 5. Cấu Trúc Thư Mục Dự Án (Directory Structure)

```text
Laravel_Web_QuanLyCuaHangGiatUi/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/           # Controllers quản trị Web (Orders, Services, Bookings, RBAC...)
│   │   │   └── Api/             # Controllers API cho Mobile App (Auth, BookingApi, OrderApi...)
│   │   └── Middleware/          # Middleware kiểm tra Phân quyền & Role
│   ├── Models/                  # Eloquent Models (User, Order, Service, Booking, Voucher...)
│   └── Policies/                # Gate Policies kiểm soát quyền thao tác dữ liệu
├── database/
│   ├── migrations/              # Database schema migrations
│   └── seeders/                 # Seeders tạo Roles, Permissions, Admin & Dữ liệu mẫu
├── resources/
│   ├── views/
│   │   ├── admin/               # Blade Templates giao diện Admin Panel (Dashboard, Orders...)
│   │   ├── components/          # Reusable UI components (Modals, Buttons, Badges)
│   │   └── layouts/             # Master Layouts (Header, Sidebar, Footer)
├── routes/
│   ├── web.php                  # Routes dành cho Giao diện Web Admin
│   └── api.php                  # Routes dành cho RESTful API Mobile
├── tests/
│   └── Feature/
│       └── PaidRecordsAreLockedTest.php # Automated Test Suite kiểm thử khóa đơn đã thanh toán
└── README.md
```

---

## 🛠️ 6. Hướng Dẫn Cài Đặt & Chạy Hệ Thống

### ⚙️ Yêu cầu môi trường:

* PHP >= 8.1
* Composer >= 2.0
* MySQL / MariaDB >= 8.0
* Web Server: Laragon / XAMPP / Nginx

### 🚀 Các bước cài đặt:

1. **Clone repository & Cài đặt dependencies**:
```bash
git clone <repository_url>
cd Laravel_Web_QuanLyCuaHangGiatUi
composer install
npm install && npm run build

```

2. **Cấu hình môi trường (`.env`)**:
```bash
cp .env.example .env
php artisan key:generate

```

*Cập nhật thông số kết nối CSDL trong file `.env`:*
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_quanlygiatui
DB_USERNAME=root
DB_PASSWORD=

```

3. **Khởi tạo Database & Dữ liệu mẫu (Seeders)**:
```bash
php artisan migrate:fresh --seed

```

4. **Chạy ứng dụng Local**:
```bash
php artisan serve

```

*Truy cập Web Admin tại:* `http://127.0.0.1:8000`

---

## 🧪 7. Chạy Kiểm Thử Tự Động (Automated Testing)

Dự án đi kèm bộ test suite kiểm thử chặt chẽ quy tắc khóa dữ liệu tài chính và phân quyền:

```bash
# Chạy bộ test kiểm thử đặc quyền Chủ cửa hàng & Khóa đơn đối với Quản lý
php artisan test --filter=PaidRecordsAreLockedTest

```

---

## 📝 8. Nhật Ký Cập Nhật Phiên Bản (Changelog v2.0)

* 🎨 **UI/UX**: Đồng nhất font chữ, chuẩn hóa màu sắc gradient Dashboard, cố định vị trí các nút hành động, thay thế confirm mặc định bằng SweetAlert2.
* 🔒 **Security & RBAC**: Triển khai mô hình Dynamic RBAC với Spatie Permissions (`roles`, `permissions`). Khóa quyền Sửa/Xóa đơn đã thanh toán với Manager/Staff.
* ⚙️ **Automation**: Tự động sinh `Order` khi duyệt `Booking`. Chuẩn hóa danh mục Dịch vụ - Giá đẻ lưu động trong CSDL.
* 🌐 **API**: Tối ưu hóa chuỗi trả về JSON, refactor mối quan hệ Eloquent `->customer`, `->order` phục vụ đồng bộ dữ liệu Web & Mobile.

---

⚠️ **LƯU Ý:** Sau khi ghi nội dung file `README.md`, TUYỆT ĐỐI CHƯA thực hiện lệnh `git commit` hay `git push`.