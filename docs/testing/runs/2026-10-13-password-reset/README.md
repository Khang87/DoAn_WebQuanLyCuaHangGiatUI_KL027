# Kiểm chứng quên mật khẩu hai bước — 13/10/2026

## Thay đổi
- `/forgot-password`: gửi/xác thực OTP, TTL 15 phút, cooldown email 60 giây do backend giữ; UI countdown nhận số giây còn lại khi tải/F5.
- `/reset-password`: chỉ mật khẩu và xác nhận; GET/POST cần grant gắn ID phiên sau regenerate, TTL 15 phút sau xác nhận, dùng một lần.
- Resend thay generation, vô hiệu grant cũ; đổi email/mật khẩu tài khoản làm grant cũ mất hiệu lực.
- Tối đa 5 lần OTP sai; IP throttle riêng gửi (5/phút), verify/reset (10/phút). OTP hash, không flash/log mã; remembered login bị thu hồi sau đổi mật khẩu.
- OTP nội bộ giữ controller/service/view hiện tại. README và spec được cập nhật.

## Kết quả
| Kiểm chứng | Kết quả |
|---|---|
| RED trước triển khai | 9 test luồng mới thất bại trên code cũ |
| Full PHPUnit, PHP 8.4.26 | 496 tests / 2645 assertions, đạt |
| Frontend Node + Chromium | 26 tests, đạt |
| Vite production build | Đạt, có entry password-reset |
| Laravel Pint | Đạt sau định dạng các file thay đổi |
| git diff --check | Đạt |
| HTTP/browser thật | Laravel session + SQLite + mailer giả: gating, countdown, verify chuyển trang, hai ô mật khẩu, layout mobile 390px không tràn ngang, cập nhật và chặn replay: đạt |
| Mutation trong bản sao container | Bỏ session_digest guard làm test copied grant thất bại (GET sai trả 200); mã workspace không bị mutation |
| Review độc lập | Lỗi grant không gắn phiên phát hiện và sửa; lượt kiểm tra cuối không còn Required với độ tin cậy cao |

## Phạm vi
Không dùng database production hay gửi email Resend thật trong kiểm thử. Browser thay riêng mail transport để đọc OTP giả; controller/service/routes/Blade và JS build chạy thật. Full PHPUnit dùng dữ liệu SQLite/fixture theo repository, không phải kiểm chứng PostgreSQL production hay deliverability email thật. Không thay schema/RLS/dependency hoặc triển khai production trong nhiệm vụ này.
