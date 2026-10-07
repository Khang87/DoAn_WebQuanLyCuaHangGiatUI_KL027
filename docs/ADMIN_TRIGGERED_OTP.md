# Admin-triggered OTP và Thông báo mặc định

## Cấu trúc dữ liệu và triển khai

Không có migration mới, DDL, bảng mới hoặc thay đổi cột. `User` ánh xạ bảng `TaiKhoan`; thông báo sử dụng bảng `ThongBao`. `Quyen`, `VaiTro` và `VaiTro_Quyen` chỉ được bổ sung dữ liệu phân quyền. Theo xác nhận của người dùng, OTP tạm thời được lưu trong Redis hiện có thay vì tạo `cache` hoặc `password_reset_tokens` trên Supabase.

Cấu hình production:

```dotenv
INTERNAL_OTP_CACHE_STORE=redis
# Giữ nguyên thông tin kết nối Redis đã cấu hình.
# Email dùng dịch vụ Resend hiện có:
RESEND_API_KEY=...
RESEND_FROM_EMAIL=...
```

Tên biến khóa API thực tế xem `config/services.php` / `.env.example`; dùng sender đã xác minh. Nếu đang cấu hình `RESEND_SANDBOX_TO_EMAIL`, chỉ địa chỉ sandbox đó nhận được email.

Sau khi triển khai, chạy lệnh bổ sung dữ liệu sau bằng kết nối database của ứng dụng:

```bash
php artisan notifications:grant-default
```

Lệnh chạy trong transaction, có thể chạy lại nhiều lần. Nó tạo/kích hoạt `NOTIFICATION_VIEW` và gán cho **mọi nhóm**, kể cả nhóm khách hàng, nhóm đã ngừng hoạt động và nhóm tùy chỉnh. Không thay đổi các quyền khác. Nhóm ngừng hoạt động/tài khoản bị khóa vẫn bị middleware chặn. Không chạy `php artisan migrate` cho thay đổi này. `RbacCatalogSeeder` cũng bổ sung quyền mặc định khi nạp danh mục.

Khi tạo nhóm từ giao diện, `RoleService::createRole()` tạo nhóm và gán quyền mặc định trong cùng transaction. Các luồng lưu ma trận, lưu quyền của nhóm và lưu nhóm của quyền đều duy trì quyền này; UI hiển thị ô mặc định đã chọn và khóa chỉnh sửa. Chỉ quyền xem/đọc **thông báo của chính mình** được gán mặc định; quyền tạo, sửa, xóa thông báo vẫn cần quyền quản trị riêng.

Laravel scheduler đã có tác vụ mỗi phút xóa nội dung thông báo OTP quá hạn. Cần chạy scheduler (`php artisan schedule:run` mỗi phút) nếu muốn tự động xóa vật lý. Dù scheduler chưa chạy, global scope của `ThongBao` vẫn ẩn OTP sau 10 phút khỏi danh sách, chi tiết, dropdown và đếm chưa đọc.

## Luồng thực hiện

1. Admin có `accounts.reset_password` chọn tài khoản tại màn hình chi tiết và nhấn gửi mã. Server lấy tài khoản bằng ID, không lấy email người nhận từ dữ liệu gửi lên.
2. Tài khoản nội bộ được nhận diện bằng `NhanVienID` có giá trị, `KhachHangID` rỗng, đang hoạt động và có email hợp lệ/không trùng (so sánh không phân biệt hoa thường). Nhóm tùy chỉnh vẫn dùng được vì không nhận diện nội bộ bằng tên nhóm.
3. `InternalPasswordOtpService` tạo OTP 6 chữ số bằng `random_int()`. Redis chỉ lưu hash mật khẩu của OTP cùng account ID, số lần thử và mốc hết hạn; key sử dụng hash email chuẩn hóa. TTL 600 giây; gửi lại tối đa một lần mỗi 60 giây. Một email chỉ có một mã đang hiệu lực.
4. `InternalPasswordResetOtp` gửi đồng bộ qua hai channel: `InternalOtpDatabaseChannel` ghi vào `ThongBao` với `TaiKhoanID` của người nhận; `InternalOtpMailChannel` gửi đúng mã qua Resend. Không đưa OTP plaintext vào queue hoặc flash session. `toMail()` và `toDatabase()` mô tả nội dung hai kênh; mail channel dùng tích hợp Resend đang có của dự án, không dùng mailer `log` mặc định.
5. Nếu gửi email hoặc ghi notification lỗi, thao tác thất bại, OTP Redis bị xóa, transaction notification rollback và mật khẩu giữ nguyên. Email là dịch vụ ngoài transaction: nếu provider đã nhận mail nhưng kết nối mất hoặc commit DB thất bại, email đó có thể vẫn tới, nhưng mã đã bị vô hiệu hóa và Admin phải cấp lại.
6. Người nhận mở `/internal/reset-password`, nhập email + OTP + mật khẩu mới/xác nhận (tối thiểu 8 ký tự). Không cần đăng nhập vì đây là luồng khôi phục tài khoản. Route POST có throttle 10 lần/phút theo IP.
7. Service kiểm tra tài khoản còn hoạt động, còn là nội bộ và email chưa đổi. Mỗi mã cho phép tối đa 5 lần nhập sai; cập nhật số lần thử không kéo dài TTL. Redis lock theo email bảo vệ cả gửi lẫn đổi mật khẩu trước các yêu cầu đồng thời.
8. Khi OTP đúng, service tiêu thụ mã trước khi cập nhật `TaiKhoan.MatKhau` bằng `Hash::make()`, xóa thông báo OTP của người nhận và thu hồi remembered-login token. Mã đã dùng không thể dùng lại. Nếu DB lỗi sau khi tiêu thụ mã, phải cấp mã mới; không khôi phục mã đã dùng. Việc đổi mật khẩu không xóa mọi phiên đăng nhập đang hoạt động của các thiết bị khác.

Luồng OTP tự yêu cầu hiện có tại `/forgot-password` và `/reset-password` được giữ riêng; mã của luồng đó không xác thực được tại endpoint nội bộ và ngược lại.

## Mã nguồn chính

| Thành phần | File |
|---|---|
| Controller Admin | `app/Http/Controllers/Admin/TaiKhoanController.php::resetPassword` |
| Controller xác thực | `app/Http/Controllers/Auth/InternalPasswordResetController.php` |
| Redis, TTL, giới hạn lần thử, đổi mật khẩu | `app/Services/InternalPasswordOtpService.php` |
| Notification | `app/Notifications/InternalPasswordResetOtp.php` |
| Email channel | `app/Notifications/Channels/InternalOtpMailChannel.php` |
| Database channel cho schema hiện có | `app/Notifications/Channels/InternalOtpDatabaseChannel.php` |
| Mailer Resend | `app/Services/ResendOtpMailer.php` |
| Quyền mặc định/backfill dữ liệu | `app/Services/DefaultNotificationPermission.php` |
| Giữ quyền qua thao tác quản lý nhóm | `app/Services/RoleService.php` |
| Quy đổi mã quyền | `app/Support/QuyenMapper.php` |
| Hộp thư cá nhân | `app/Http/Controllers/Admin/ThongBaoController.php`, `app/Services/NotificationService.php` |
| Ẩn thông báo OTP hết hạn | `app/Models/ThongBao.php` |
| Form đổi mật khẩu | `resources/views/auth/passwords/reset.blade.php` |
| Lệnh backfill và tác vụ dọn mã hết hạn | `routes/console.php` |
| Cấu hình Redis | `config/cache.php`, `.env.example` |

## Routes

| Method | URL | Route name | Kiểm soát |
|---|---|---|---|
| POST | `/accounts/{account}/reset-password` | `accounts.reset-password` | auth, reject.customer, accounts.reset_password, policy cập nhật tài khoản |
| GET | `/internal/reset-password` | `internal-password.reset` | Form công khai, không trả OTP |
| POST | `/internal/reset-password` | `internal-password.update` | CSRF, throttle:10,1, OTP một lần |
| GET | `/notifications` | `notifications.index` | auth, notifications.view; chỉ dữ liệu của người đăng nhập |
| GET | `/notifications/{notification}` | `notifications.show` | auth, notifications.view; kiểm tra người nhận |
| PATCH | `/notifications/{notification}/mark-read` | `notifications.mark-read` | auth, notifications.view; kiểm tra người nhận |
| POST | `/notifications/mark-all-read` | `notifications.mark-all-read` | auth, notifications.view; chỉ của người đăng nhập |

Nhóm mới chỉ có quyền Thông báo có thể đăng nhập và được chuyển tới hộp thư thay vì bị báo chưa có quyền. Khách hàng có quyền mặc định cũng có thể đăng nhập web để đọc hộp thư cá nhân; các module vận hành vẫn bị `reject.customer` chặn.

Các route đọc thông báo nằm ngoài `reject.customer` để nhóm khách hàng cũng truy cập hộp thư cá nhân. Gửi `user_id`/`id` của người khác không làm lộ hoặc đánh dấu thông báo của họ. Ngay cả Owner cũng không xem được OTP gửi cho người khác. Các route quản lý thông báo không cho sửa, chuyển người nhận, xóa hay giả mạo loại `internal_password_otp`.

## Kiểm thử và giới hạn môi trường

`tests/Feature/InternalOtpTest.php` kiểm thử hai kênh, thời hạn 10 phút, sai 5 lần, dùng một lần, cấp lại, rollback khi gửi lỗi, quyền Admin, tài khoản khách hàng, email thay đổi/tài khoản bị khóa, chống đọc/đánh dấu OTP người khác, quyền mặc định qua mọi trình chỉnh sửa và hộp thư nhóm khách hàng. Các bài AuthFlow và phân quyền động hiện có được chạy lại.

Kiểm thử tự động dùng SQLite in-memory và cache array/file, mock Resend; không gửi email thật, không sửa Supabase thật. Chưa xác minh kết nối Redis production, email thực tế hoặc chạy backfill trên Supabase vì môi trường làm việc chưa có credentials của các dịch vụ đó.

Kết quả kiểm chứng ngày 2026-10-07: **275 passed, 192 skipped, 0 failed (1472 assertions)**. Laravel Pint, route cache và Blade view cache đều thành công. Các bài skipped là bộ legacy đã bị bỏ qua trong cấu hình có sẵn.
