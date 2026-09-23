# TÀI LIỆU KỊCH BẢN KIỂM THỬ (TEST CASES) - HỆ THỐNG QUẢN LÝ CỬA HÀNG GIẶT ỦI

Tài liệu này ghi nhận toàn bộ các Test Case kiểm thử tự động (Automated Feature Tests) và kiểm thử chức năng cho hệ thống Website Quản lý cửa hàng giặt ủi. Các test case mới phát triển về sau sẽ được tiếp tục cập nhật và bổ sung vào tài liệu này.

---

## 1. Phân Hệ Xác Thực & Tài Khoản (Authentication)
**File Test**: `tests/Feature/AuthTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-AUTH-01** | Kiểm tra hiển thị màn hình đăng nhập | Kiểm tra route `/login` hoạt động bình thường | GET `/login` | Trả về HTTP status 200, hiển thị form đăng nhập | PASSED |
| **TC-AUTH-02** | Đăng nhập thành công với thông tin hợp lệ | Kiểm tra đăng nhập khi nhập đúng Email & Mật khẩu | User hợp lệ (`password123`) | Đăng nhập thành công, chuyển hướng về Dashboard (`/dashboard`) | PASSED |
| **TC-AUTH-03** | Đăng nhập thất bại khi sai mật khẩu | Kiểm tra hệ thống từ chối đăng nhập khi sai mật khẩu | Mật khẩu: `wrong-password` | Không cho đăng nhập (Guest), giữ lại trang login với thông báo lỗi | PASSED |
| **TC-AUTH-04** | Đăng xuất khỏi hệ thống | Kiểm tra chức năng đăng xuất của người dùng | POST `/logout` (khi đã login) | Đăng xuất thành công, hủy session, chuyển hướng về `/login` | PASSED |

---

## 2. Phân Hệ Quản Lý Khách Hàng (Customer Management)
**File Test**: `tests/Feature/CustomerControllerTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-CUST-01** | Xem danh sách khách hàng | Kiểm tra hiển thị danh sách khách hàng trên giao diện Admin | GET `route('customers.index')` | Trả về HTTP status 200, hiển thị thông tin khách hàng | PASSED |
| **TC-CUST-02** | Thêm mới khách hàng | Kiểm tra lưu thông tin khách hàng mới vào CSDL | Name, Email, Phone, Address, Type | Thêm thành công, tạo mã `KHxxx`, chuyển hướng về danh sách khách hàng | PASSED |
| **TC-CUST-03** | Xem chi tiết thông tin khách hàng | Kiểm tra hiển thị trang chi tiết khách hàng theo ID | GET `route('customers.show', $id)` | Trả về HTTP status 200, hiển thị đầy đủ thông tin khách hàng | PASSED |
| **TC-CUST-04** | Cập nhật thông tin khách hàng | Kiểm tra thay đổi thông tin khách hàng trong CSDL | Name mới, Email mới | Cập nhật thành công vào DB, chuyển hướng về danh sách khách hàng | PASSED |
| **TC-CUST-05** | Xóa khách hàng | Kiểm tra xóa tài khoản khách hàng khỏi hệ thống | DELETE `route('customers.destroy', $id)` | Xóa khách hàng khỏi DB, chuyển hướng về danh sách khách hàng | PASSED |

---

## 3. Phân Hệ Quản Lý Đơn Hàng (Order Management)
**File Test**: `tests/Feature/OrderControllerTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-ORD-01** | Xem danh sách đơn hàng | Kiểm tra hiển thị danh sách đơn hàng giặt ủi | GET `route('orders.index')` | Trả về HTTP status 200, hiển thị mã đơn hàng `#DHxxx` | PASSED |
| **TC-ORD-02** | Tạo đơn hàng mới | Kiểm tra khởi tạo đơn giặt ủi cho khách hàng | customer_id, service_id, weight_kg, total_amount, status | Lưu đơn hàng thành công vào CSDL, tự động sinh mã `#DHxxx` | PASSED |
| **TC-ORD-03** | Xem chi tiết đơn hàng | Kiểm tra hiển thị chi tiết thông tin đơn giặt ủi | GET `route('orders.show', $id)` | Trả về HTTP status 200, bao gồm thông tin khách hàng & dịch vụ | PASSED |
| **TC-ORD-04** | Cập nhật trạng thái đơn hàng | Kiểm tra thay đổi trạng thái/thông tin đơn hàng | Status: `completed` | Cập nhật trạng thái thành công trong CSDL | PASSED |
| **TC-ORD-05** | Xóa đơn hàng | Kiểm tra xóa đơn hàng khỏi hệ thống | DELETE `route('orders.destroy', $id)` | Xóa bản ghi đơn hàng thành công khỏi CSDL | PASSED |

---

## 4. Phân Hệ Quản Lý Dịch Vụ (Service Management)
**File Test**: `tests/Feature/ServiceControllerTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-SRV-01** | Xem danh sách dịch vụ | Kiểm tra hiển thị bảng giá & danh mục dịch vụ | GET `route('services.index')` | Trả về HTTP status 200, hiển thị thông tin dịch vụ | PASSED |
| **TC-SRV-02** | Thêm mới dịch vụ giặt ủi | Kiểm tra thêm dịch vụ giặt ủi mới vào CSDL | Name, Type, Price, Unit, Status, Description | Lưu thành công vào DB, chuyển hướng về danh sách | PASSED |
| **TC-SRV-03** | Cập nhật thông tin dịch vụ | Kiểm tra thay đổi tên/đơn giá dịch vụ | Name mới, Price mới | Cập nhật thông tin dịch vụ trong CSDL thành công | PASSED |
| **TC-SRV-04** | Xóa dịch vụ giặt ủi | Kiểm tra xóa dịch vụ khỏi danh mục | DELETE `route('services.destroy', $id)` | Xóa dịch vụ thành công khỏi CSDL | PASSED |

---

## 5. Phân Hệ Quản Lý Giao Nhận (Delivery Management)
**File Test**: `tests/Feature/DeliveryControllerTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-DEL-01** | Xem danh sách lịch giao nhận | Kiểm tra hiển thị danh sách nhận đồ / giao tận nơi | GET `route('deliveries.index')` | Trả về HTTP status 200 | PASSED |
| **TC-DEL-02** | Tạo yêu cầu giao nhận mới | Kiểm tra tạo lịch giao nhận đồ giặt cho khách | customer_id, method, address, pickup_date, pickup_time | Đặt lịch giao nhận thành công vào CSDL | PASSED |
| **TC-DEL-03** | Xóa lịch giao nhận | Kiểm tra hủy/xóa lịch giao nhận | DELETE `route('deliveries.destroy', $id)` | Xóa bản ghi giao nhận khỏi CSDL | PASSED |

---

## 6. Phân Hệ Quản Lý Tài Khoản Hệ Thống (User / Account Management)
**File Test**: `tests/Feature/AccountControllerTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-ACC-01** | Xem danh sách tài khoản | Admin xem danh sách tài khoản hệ thống | GET `route('accounts.index')` | Trả về HTTP status 200, hiển thị các tài khoản hệ thống | PASSED |
| **TC-ACC-02** | Tạo tài khoản có vai trò xác định | Admin tạo tài khoản gán vai trò cụ thể | Name, Email, Password, Role | Tạo tài khoản thành công, gán vai trò được chọn | PASSED |
| **TC-ACC-03** | Tạo tài khoản mặc định là Khách hàng | Tạo tài khoản mới khi không chọn vai trò | Name, Email, Password | Tạo tài khoản thành công, vai trò mặc định là `customer` (Khách hàng) | PASSED |
| **TC-ACC-04** | Ngăn chặn tài khoản tự xóa chính mình | Bảo vệ tài khoản đang đăng nhập không bị xóa | DELETE `accounts.destroy` trên chính Admin | Trả về HTTP status 422, từ chối xóa tài khoản | PASSED |
| **TC-ACC-05** | Xóa tài khoản người dùng khác | Admin xóa tài khoản người dùng khác | DELETE `accounts.destroy` đối với User khác | Xóa thành công tài khoản khỏi CSDL | PASSED |

---

## 7. Phân Hệ Dashboard & Hệ Thống (Dashboard & Core System)
**File Test**: `tests/Feature/DashboardControllerTest.php` & `ExampleTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-DASH-01** | Truy cập Dashboard khi đã đăng nhập | Kiểm tra tải trang tổng quan Dashboard | GET `route('dashboard')` (Auth User) | Trả về HTTP status 200, hiển thị các thông số thống kê | PASSED |
| **TC-DASH-02** | Bảo vệ route Dashboard khi chưa đăng nhập | Kiểm tra Middleware ngăn người dùng vãng lai | GET `route('dashboard')` (Guest) | Chuyển hướng người dùng về trang đăng nhập `/login` | PASSED |
| **TC-SYS-01** | Kiểm tra trang đăng nhập hệ thống | Verification cơ bản của ứng dụng | GET `/login` | Trả về HTTP status 200 | PASSED |

---

## 8. Phân Hệ Quản Lý Sản Phẩm / Loại Đồ Giặt & Hiện Trạng (Garment Management)
**File Test**: `tests/Feature/GarmentControllerTest.php`

| Mã Test Case | Tên Test Case | Mô Tả | Dữ Liệu Đầu Vào | Kết Quả Mong Đợi | Trạng Thái |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **TC-GAR-01** | Xem danh sách loại đồ giặt | Kiểm tra hiển thị danh sách sản phẩm & loại đồ giặt | GET `route('garments.index')` | Trả về HTTP status 200, hiển thị loại đồ giặt | PASSED |
| **TC-GAR-02** | Thêm mới loại đồ giặt | Kiểm tra thêm loại đồ giặt mới và hiện trạng trước giặt | Name, Category, Price, Condition_note, Status | Thêm thành công loại đồ giặt vào CSDL | PASSED |
| **TC-GAR-03** | Xem chi tiết loại đồ giặt | Kiểm tra xem chi tiết loại đồ giặt & ghi chú hiện trạng | GET `route('garments.show', $id)` | Trả về HTTP status 200, hiển thị đầy đủ hiện trạng | PASSED |
| **TC-GAR-04** | Cập nhật loại đồ giặt | Kiểm tra cập nhật giá & tên loại đồ giặt | Name mới, Price mới | Cập nhật thông tin loại đồ giặt trong CSDL thành công | PASSED |
| **TC-GAR-05** | Xóa loại đồ giặt | Kiểm tra xóa loại đồ giặt | DELETE `route('garments.destroy', $id)` | Xóa loại đồ giặt thành công khỏi CSDL | PASSED |

---
*Ghi chú: Khi tạo thêm test mới trong tương lai, hãy bổ sung các mã Test Case tương ứng vào phân hệ thích hợp trong tài liệu này.*
