# BỐI CẢNH & YÊU CẦU CHỨC NĂNG: HỆ THỐNG QUẢN LÝ / NHÂN VIÊN

## 0. QUY TẮC THỰC THI DÀNH CHO AI AGENT
- **Đường dẫn dự án tuyệt đối:** `D:\laragon\www\Web_Laravel_QuanLyGiatUi\Laravel_Web_QuanLyCuaHangGiatUi`
- **Môi trường:** Laravel 11 / PHP 8.2 / MySQL / Bootstrap 5 / FontAwesome.
- **Quy định khởi tạo:** Tuyệt đối KHÔNG báo lỗi "File not found" đối với các Model/Controller/Request chưa tồn tại. Hãy tự động tạo mới file bằng artisan command hoặc tạo file theo đúng namespace chuẩn Laravel.
- **Ràng buộc lưu trữ:** Luôn bọc các thao tác ảnh hưởng nhiều bảng trong `DB::transaction`. Sử dụng FormRequest để validate dữ liệu.

---

## 1. TỔNG QUAN PHÂN HỆ QUẢN LÝ (ADMIN & STAFF)
Phân hệ dành cho Quản trị viên (Admin) và Nhân viên (Staff) vận hành hệ thống cửa hàng giặt ủi.

---

## 2. CHI TIẾT CÁC MODULE NGHIỆP VỤ

### Module 1: Quản lý Tài khoản & Phân quyền (`User`, `Role`)
* **Chức năng:**
  * Quản lý danh sách tài khoản: Khách hàng (`customer`), Nhân viên (`staff`), Quản trị viên (`admin`).
  * CRUD (Thêm, Xem, Cập nhật, Xóa/Khóa) tài khoản người dùng.
  * Phân quyền truy cập theo Vai trò (Role-based Access Control - RBAC) sử dụng Laravel Gate/Policy.
  * Đăng nhập, Đăng xuất, Đổi mật khẩu, Reset mật khẩu cho nhân viên/khách hàng.

### Module 2: Quản lý Hồ sơ Khách hàng (`Customer`)
* **Chức năng:**
  * Quản lý thông tin cá nhân khách hàng (Họ tên, Số điện thoại, Email, Địa chỉ).
  * Tìm kiếm và lọc thông tin khách hàng nhanh theo SĐT hoặc Tên.
  * Xem lịch sử đơn hàng và tổng chi tiêu của từng khách hàng.

### Module 3: Quản lý Danh mục & Chi tiết Dịch vụ (`ServiceCategory`, `Service`)
* **Chức năng:**
  * **Danh mục dịch vụ:** CRUD các nhóm dịch vụ (Giặt thường, Giặt khô, Giặt hấp, Giặt sấy, Ủi, Giặt nhanh...).
  * **Chi tiết dịch vụ:** CRUD dịch vụ cụ thể kèm mô tả, đơn giá, thời gian xử lý dự kiến, ghi chú đặc biệt, icon FontAwesome.
  * **Đơn vị tính:** Quản lý linh hoạt đơn vị tính (`kg`, `món`, `combo`).
  * **Bảng giá:** Thiết lập và quản lý giá dịch vụ theo từng loại đơn vị.

### Module 4: Quản lý Loại đồ giặt / Sản phẩm (`Garment`, `GarmentCondition`)
* **Chức năng:**
  * CRUD các loại đồ giặt (Áo dài, Váy cưới, Áo khoác, Quần âu, Chăn ga, Đồ thường...).
  * **Ghi nhận hiện trạng:** Cho phép ghi nhận, chụp/mô tả hiện trạng sản phẩm trước khi giặt (Rách, Bẩn nặng, Phai màu, Mất cúc...).
  * **Định giá nâng cao:** Cấu hình phụ thu hoặc giá dịch vụ riêng theo từng loại đồ giặt đặc biệt.

### Module 5: Quản lý Đơn hàng Giặt ủi (`Order`, `OrderItem`)
* **Chức năng:**
  * Hiển thị danh sách đơn hàng với bộ lọc (Mã đơn, Khách hàng, Trạng thái, Ngày tạo).
  * Tạo đơn giặt ủi trực tiếp tại tiệm cho khách hàng (POS/Form).
  * Cập nhật trạng thái đơn hàng theo luồng xử lý: `Chờ xác nhận` $\rightarrow$ `Đã nhận đồ` $\rightarrow$ `Đang giặt` $\rightarrow$ `Đã giặt xong` $\rightarrow$ `Đang giao` $\rightarrow$ `Hoàn thành` / `Đã hủy`.
  * Theo dõi lịch sử thay đổi trạng thái đơn hàng.

### Module 6: Quản lý Đặt lịch & Giao nhận (`Booking`, `Delivery`)
* **Chức năng:**
  * Quản lý thông tin đặt lịch giặt online của khách hàng (Loại đồ, Số lượng dự kiến, Dịch vụ yêu cầu).
  * Quản lý hình thức giao nhận: `Nhận tại tiệm` hoặc `Giao tận nơi`.
  * Điều phối nhân viên giao nhận, ghi nhận địa chỉ và thời gian hẹn lấy/giao đồ.

### Module 7: Quản lý Thanh toán (`Payment`, `Invoice`)
* **Chức năng:**
  * Tạo hóa đơn (`Invoice`) và quản lý thanh toán cho đơn hàng.
  * Hỗ trợ các phương thức thanh toán: `Tiền mặt`, `Chuyển khoản (QR Code)`, `Ví điện tử`.
  * Theo dõi trạng thái thanh toán: `Chưa thanh toán`, `Thanh toán một phần`, `Đã thanh toán`.

### Module 8: Quản lý Khuyến mãi & Mã giảm giá (`Promotion`, `Coupon`)
* **Chức năng:**
  * Tạo và quản lý các chương trình khuyến mãi (Giảm %, Giảm tiền trực tiếp, Miễn phí giao hàng).
  * Tạo và quản lý mã giảm giá (`Coupon Code`), thiết lập hạn sử dụng và số lần sử dụng tối đa.
  * Áp dụng tự động hoặc nhập mã giảm giá khi tính tiền đơn hàng.

### Module 9: Báo cáo & Thống kê (`Report`)
* **Chức năng:**
  * Báo cáo doanh thu theo ngày, tuần, tháng, năm.
  * Thống kê số lượng đơn hàng theo trạng thái và theo dịch vụ phổ biến.
  * Thống kê top khách hàng có chi tiêu cao nhất.

---

## 3. CHECKLIST KIỂM TRA MÃ NGUỒN CHO KILO
- [ ] Database Migration đầy đủ khóa chính, khóa ngoại, index và `softDeletes()`.
- [ ] Model có khai báo `$fillable`, `casts`, và các quan hệ (`hasMany`, `belongsTo`).
- [ ] FormRequest thực hiện validation dữ liệu đầu vào chuẩn xác.
- [ ] Controller áp dụng Service Pattern, tránh viết logic nặng ở Controller.
- [ ] View Blade có giao diện Responsive (Bootstrap 5) và bảo mật form `@csrf`.