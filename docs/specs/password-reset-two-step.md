# Quên mật khẩu công khai: hai bước

## Mục tiêu và phạm vi
Laravel session/Blade hiện tại; giữ Resend và cache auth dùng chung. Bước 1 `/forgot-password`: email, gửi OTP, OTP 6 số, xác nhận; mã 15 phút, resend 60 giây có countdown. Bước 2 `/reset-password`: chỉ hai trường mật khẩu, được vào sau OTP hợp lệ. Luồng OTP nội bộ 10 phút giữ nguyên.

## Hợp đồng và tiêu chí chấp nhận
- POST gửi mã giới hạn theo email bằng atomic cache/cooldown, thêm throttle IP. GET/F5 giữ deadline thực do server cấp; UI không là nguồn rate limit.
- POST xác nhận tiêu thụ OTP đúng một lần dưới lock; sai/hết hạn không cấp quyền. Tối đa 5 lần sai/mã và throttle IP xác nhận.
- Cấp grant ngẫu nhiên gắn session, TTL 15 phút sau xác nhận, chỉ dùng một lần. Không nhận email/OTP/token do client khai ở bước đặt mật khẩu. Grant vô hiệu khi resend hoặc mật khẩu tài khoản thay đổi; không chuyển được sang tài khoản khác.
- GET/POST bước 2 khi không có grant hợp lệ đều quay về bước 1. Password >=8 và confirmed như hợp đồng hiện tại. Thành công thu hồi remembered login và xóa session grant.
- Mã chỉ lưu hash, không đưa vào HTML/log. Email chưa đăng ký nhận thông báo cùng dạng để tránh dò tài khoản. Lỗi provider giữ thông báo an toàn hiện tại và thu hồi OTP/cooldown.
- Hai trang dùng thiết kế login hiện có, label/autocomplete phù hợp, không hiển thị OTP cùng form mật khẩu. Nội bộ vẫn dùng view cũ.

## Cấu trúc và phong cách
Controller điều phối session/redirect; PasswordResetOtpService sở hữu cooldown, OTP, grant và thay đổi mật khẩu. Blade ở auth/passwords; JS countdown entry riêng, không thư viện mới. PHP theo Pint; tên phương thức verify/canReset/reset rõ trách nhiệm.

## Kế hoạch và kiểm chứng
1. Viết contract tests HTTP cho gating, TTL, replay, resend, sai mã, account tamper, validation, provider failure.
2. Service/cache lock và controller/routes; sau đó hai Blade và JS countdown.
3. Chạy `php vendor/bin/phpunit --filter 'AuthFlowTest|PasswordResetTwoStepTest|InternalOtpTest'`, `node --test tests/Frontend/*.test.mjs`, `npm run build`, Pint; full PHPUnit; browser local với mailer giả, không gửi OTP thật.
4. Kiểm thử mutation bỏ guard bước 2 để xác nhận test bắt được bypass.

## Ranh giới
Chỉ sửa public forgot-password; không thay DB, RLS, OTP nội bộ hay dependency. Không gửi email production trong kiểm thử. Report nghiệp vụ chưa commit giữ riêng. Yêu cầu người dùng đã định nghĩa UI/backend đủ rõ để triển khai trực tiếp.
