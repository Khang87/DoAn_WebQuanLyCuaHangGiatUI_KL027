# Hoàn thiện giao diện, chat và Booking — 09/10/2026

Phạm vi: web Laravel; giữ session, quyền hiện hành, inspection-first, luồng legacy và API/RPC mobile. Đặc tả: [readiness](../../../specs/readiness/README.md).

| Mục | Kết quả |
| --- | --- |
| 1 | Chi tiết thông báo có nút quay lại danh sách. |
| 2 | Nhân viên xem/gửi cuộc trò chuyện hỗ trợ với khách chưa có đơn; giữ `DonHangID=NULL`, tương thích RPC mobile hiện có. |
| 3 | Chuông thông báo đọc API Laravel mỗi 15 giây, chỉ lấy thông báo của tài khoản đăng nhập. |
| 4 | Bổ sung kiểm thử HTTP/session/quyền; sửa RPC cho lỗi tràn cột tin nhắn trên 1.000 ký tự. Chưa thể bảo đảm mọi môi trường production không còn lỗi 500. |
| 5 | Cuộc trò chuyện đang mở và chuông thông báo cập nhật qua polling, không cần F5; dừng khi ẩn tab/offline/mất quyền. |
| 6 | Khóa nút gửi ngay lần submit đầu, ngăn double-click và submit lặp trong lúc chờ điều hướng. Đây là bảo vệ UI, không bảo đảm idempotency cho mọi API client. |
| 7 | Dùng tên tài khoản/họ tên khách; không hiển thị định danh `auth-...`. |
| 8 | Danh sách thanh toán dùng avatar tài khoản khách liên kết, có ảnh dự phòng. |
| 9 | Sáu ô Dashboard đã dùng dữ liệu DB; kiểm thử thay đổi khách/đánh giá xác nhận KPI cập nhật. Không thay calculator hiện có. |
| 10 | Hiển thị cảnh báo khi thiếu bảng giá; chọn lại tuple hợp lệ khôi phục trường số lượng/khối lượng. |
| 11 | Tạo đơn và kiểm kê Booking có thông báo thêm dòng và focus trường mới. |
| 12 | Kiểm kê Booking có preview giá server theo ba khóa, debounce, hủy request cũ; preview không ghi đơn/điểm/quota. |
| 13 | Calculator hiện hành đã áp dụng khuyến mãi trước điểm và giữ phí giao nhận; bổ sung hồi quy preview. `schema.sql` bổ sung năm chat RPC, giữ bảng/index/identity/legacy. |

## Kiểm thử

- Strict PHPUnit: **410 tests / 2.126 assertions**, không failure/skip.
- Frontend: **11 tests**; Vite production build đạt.
- PostgreSQL: business rules, **66** Web/RPC contract assertions, **3 races / 21 assertions**; thêm **9** assertions cho giới hạn chat và phân quyền.
- Chromium + HTTP session + PostgreSQL tạm: **31 Node tests**, tính cả parent; kiểm tra các màn hình mới và hồi quy cũ. Asset ứng dụng dùng build thật; avatar Storage và dữ liệu là fixture cô lập.
- Tái hiện trước sửa: API thông báo/support thiếu route, preview thiếu method và tin 1.001 ký tự gây lỗi `varchar(1000)`. Các ca tương ứng đạt sau sửa.
- Mutation kiểm thử submit guard: bỏ điều kiện khóa gửi trên bản sao khiến test thất bại ở lần submit thứ hai; source thật giữ nguyên.
- Review độc lập không phát hiện lỗi correctness/security bắt buộc sửa trong phạm vi này.

`TESTCASES.md` có **349 ca: 348 Passed, 1 Blocked**. STT 237 vẫn bị policy môi trường chặn POST login/upload production; kết quả tự động không đồng nghĩa 349 lượt manual trên live.

## Supabase

Đã áp dụng migration riêng `align_chat_message_limit_with_storage` sau kiểm thử và kiểm tra fingerprint routine cũ. Chỉ đổi ngưỡng `send_chat_message` từ 2.000 xuống 1.000 ký tự, khớp cột lưu trữ; giữ chữ ký, kiểm tra quyền, `SECURITY DEFINER` và search path. Catalog sau migration xác nhận giới hạn mới. Không thay RLS/grants và không ghi/xóa dữ liệu nghiệp vụ.

Catalog live đối chiếu 31 bảng / 253 cột / 152 constraints; 26 sequence sở hữu không tụt sau ID hiện có. Không chạy toàn bộ `schema.sql` lên production; snapshot không thay thế migration đầy đủ cho private helpers/RLS.
