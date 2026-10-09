# Hoàn thiện web Laravel trước bảo vệ

## Phạm vi và module
| Module | Mục yêu cầu | Phụ thuộc |
| --- | --- | --- |
| notifications | 1, 3, 5 | Laravel auth, notifications.view, TinNhan/ThongBao hiện hành |
| messaging | 2, 6, 7 | messages.view/create, notifications |
| order-ui | 8, 10, 11, 12 | bảng giá 3 khóa, Booking/OrderService |
| verification | 4, 9, 13 | ba module trên, catalog PostgreSQL chỉ đọc |

Thứ tự: notifications → messaging → order-ui → verification. Không sửa Flutter (chủ dự án xác nhận lỗi trên web). Giữ legacy, Admin OTP và Web-as-Source-of-Truth.

## Hợp đồng
- Notifications: thêm nút về danh sách; GET /notifications/updates đọc riêng người nhận, tối đa5, count chưa đọc, no-store, quyền hiện hành. Chuông đồng bộ không reload mỗi15s, tabẩn/offline tạm dừng; không hiển thị nội dung OTP trong thông báo nổi. Tin nhắn mới đã có trigger production tạo ThongBao; không thêm trigger trùng.
- Messaging: chat hỗ trợ trước đặt hàng đã có RPC production với DonHangID=NULL. Web bổ sung inbox hỗ trợ và GET/POST theo customer account hợp lệ, chỉ customer active, giới hạn100, staff ACL. Giữ chat theo order và cách gửi POST/redirect. Chặn submit liên tiếp ở client tới navigation, khôi phục trên pageshow; không tuyên bố API idempotent. Hiển thị HoTen thay auth-generated username, fallback an toàn. Không dùng sender name để phân quyền.
- Order UI: avatar lấy tài khoản liên kết customer, fallback; eagerload tránh N+1. Không cho bỏ qua bảng giá; cảnh báo rõ thiếu giá và cách chọn lại. Khi thêm dòng báo qua role=status và focus. Booking inspection hiển thị ước tính server từ cùng calculator, phí sau giảm, không tin client total. Ước tính không phải hóa đơn chốt.
- Verification: dashboard6ô hiện đã động, kiểm chứng fixture thay đổi; giữ tài chính đã đúng và tests 89/492. Đối chiếu catalog live chỉ đọc và bổ sung schema.sql các cột/functions/triggers hiện thiếu. RPC send_chat_message kiểm tra2000 ký tự trong khi TinNhan.NoiDung varchar1000: tái hiện lỗi1001, kiểm thử bản sửa1000 và áp dụng riêng routine sau review; không đổi bảng để che lỗi. Không khẳng định toàn hệ thống không thể500; báo rõ phạm vi đã chạy và lỗi hạ tầng chưa tái hiện.

## Kiểm thử và quy chuẩn
PHP typed/Laravel validation + middleware, Pint; UI Bootstrap/component hiện hành, textContent khi render dữ liệu. Không thêm dependency, không lộ account model/secrets.
`php vendor/bin/phpunit --fail-on-warning --fail-on-risky`; `node --test tests/Frontend/*.test.mjs`; `npm run build`; `bash scripts/test-web-e2e.sh`; CI cả3job trước merge.
Tests SQLite memory và HTTP/PostgreSQL tạm; catalog production SELECT-only; riêng routine send_chat_message được sửa giới hạn sau kiểm thử, guard hash cũ và review. Production DDL/RPC được chuẩn bị thành SQL reviewable; không chạy destructive schema snapshot lên live.
