# Quyền giao tiếp quản trị mặc định

Tài khoản đang hoạt động có ít nhất một nhóm đang hoạt động được mặc định xem/gửi chat khách hàng và xem/nhận thông báo cá nhân trên web. Quyền này áp dụng cho nhóm hiện có và nhóm mới, không phụ thuộc VaiTro_Quyen.

Nhóm Khách hàng/Customer/Client bị loại trừ, kể cả tài khoản được gán thêm nhóm nội bộ; tài khoản/nhóm bị khóa và tài khoản chưa có nhóm không được cấp quyền. Loại trừ có ưu tiên cao hơn quyền owner và quyền gán tay. UI, HTTP endpoints và cấp cấu hình Realtime dùng cùng chính sách. Các quyền tạo/sửa/xóa thông báo và module khác vẫn theo RBAC. Thông báo/chat khách hàng trên mobile giữ nguyên.

Triển khai: chính sách tập trung → User/Gate → Blade/routes → bộ chọn người nhận thông báo nội bộ. Không thay đổi phân quyền tài chính hoặc quy tắc đơn hàng.

Hàm `notify_staff_new_booking` chỉ được gọi bởi trigger tạo Booking hoặc vai trò dịch vụ; khách mobile không được gọi trực tiếp để tạo thông báo giả.

Kiểm chứng: PHPUnit kiểm tra nhóm tùy chỉnh không có quyền DB, gửi chat thực, đọc cập nhật, loại trừ customer/alias/mixed-role, UI bị ẩn, khóa tài khoản/nhóm và quyền module khác. Chạy bộ PHPUnit và build frontend.

Commands: `php vendor/bin/phpunit`; `npm run build`.
