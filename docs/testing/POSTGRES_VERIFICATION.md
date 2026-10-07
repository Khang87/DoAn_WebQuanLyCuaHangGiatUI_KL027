# Kiểm chứng Web/RPC và đồng thời PostgreSQL

## Chạy cục bộ

Yêu cầu: Bash, Docker cục bộ, PHP 8.4 có `pdo_pgsql`, `proc_open`, `openssl`, và `vendor/` cài từ Composer lockfile. Không cần tài khoản Supabase hoặc thông tin đăng nhập production.

```bash
bash scripts/test-postgres.sh
bash scripts/test-postgres.sh contracts
bash scripts/test-postgres.sh concurrency
```

`PHP_BIN` có thể trỏ tới executable PHP/wrapper cục bộ. Runner kiểm tra extension, chạy 4 ca từ chối cấu hình, tạo container PostgreSQL 17 với tên/mật khẩu ngẫu nhiên, chỉ bind `127.0.0.1`, chờ TCP server khởi tạo hoàn tất, nạp fixture và chạy RPC assertions với `ON_ERROR_STOP` và `plpgsql.check_asserts=on`. Trap dọn đúng container ID của lần chạy. Mật khẩu không lưu trong repository; GitHub Actions che giá trị khỏi log.

Fixture SQL chỉ cho database `laundry_rpc_test`; `\ir` giúp đường dẫn include độc lập vị trí checkout. PHP yêu cầu `PG_TEST_HOST=127.0.0.1`, cổng hợp lệ, mật khẩu không rỗng, tên database cố định và marker `laundry verification fixture v1`. Sai cấu hình phải fail trước Laravel/business mutation. Test marker sai xác minh thêm database cùng tên vẫn bị từ chối. Marker là lớp bảo vệ bổ sung cho fixture cục bộ, không thay thế xác thực production.

Bootstrap PHP dùng đường dẫn environment/cache/storage tạm riêng, bỏ qua `.env` và cached config của project. Chỉ đăng ký connection test; cache/session/mail dùng driver cô lập. `phpunit.xml` vẫn buộc SQLite và không được thay bằng connection PostgreSQL.

## Những gì đã được kiểm chứng

| Bộ | Phạm vi |
|---|---|
| SQL RPC có sẵn | Giá hiệu lực, KG tối thiểu, intent điểm, idempotency, inspection bắt buộc, refund, lifecycle và replay thanh toán |
| Web/RPC | 66 assertions: cùng tuple/giá, biên ngày, ties, giá gửi giả, condition thực tế, giữ estimate, replay, ownership, nhân viên active, bốn tổ hợp nhận/trả, voucher/điểm/phí, actor thanh toán, cộng/hoàn điểm giữa Web và RPC, paid/cancelled parent |
| PostgreSQL concurrency | 3 race / 21 assertions: cùng Booking, cùng delivery leg, khác Booking nhưng chung số dư khách |
| Runner safety | 4 cấu hình sai bị chặn trước kết nối; marker sai được kiểm tra trong bộ Web/RPC |

Concurrency dùng các PHP process/connection độc lập. Phiên điều phối giữ khóa hàng, làm mới statistics snapshot và kiểm tra cả hai worker có `wait_event_type=Lock` cùng `pg_blocking_pids()` trước khi thả khóa. Sau khi hoàn tất, assertions kiểm tra ID, số dư, chi tiết, giao nhận và audit đã lưu; loser phải rollback. Startup/statement/process waits đều có deadline, worker được dọn khi lỗi. Các ca dùng dữ liệu đã commit vì process khác không thấy transaction chưa commit.

Suite sequential rollback sau khi kiểm tra; suite concurrency để lại fixture trong container dùng một lần và container được xóa khi kết thúc. Mọi exception test phải trả exit code khác 0; không dựa vào console renderer của Laravel để quyết định thành công.

Mutation đã thực hiện trên bản sao cách ly: đổi điều kiện chọn phiếu noncancelled thành cancelled khiến test duplicate leg thất bại. Source application của checkout chính không bị sửa.

## CI

`.github/workflows/verification.yml` chạy trên PR vào `main`, push `main` và manual dispatch. Hai job:

- **PHP and PostgreSQL:** syntax PHP, Pint cho file PHP thay đổi, PHPUnit với `--fail-on-warning --fail-on-risky`, Blade compile, runner PostgreSQL, Composer audit.
- **Frontend and build:** `npm ci --ignore-scripts`, Chromium controls và security regression, native Vite build, npm audit mức high trở lên.

CI dùng PHP 8.4, Node 22, PostgreSQL 17, `APP_URL=http://localhost:8000`, driver test, application key ngẫu nhiên và storage directories tạo rõ ràng. Actions được pin bằng SHA; quyền repository chỉ `contents: read`. Không dùng production secrets, `continue-on-error` hoặc bỏ assertions.

Để lỗi CI **bắt buộc chặn merge**, quản trị repository cần bật required checks cho **PHP and PostgreSQL** và **Frontend and build** trong ruleset/branch protection của `main`. File workflow tự nó chưa cấu hình ruleset. Chỉ gọi CI xanh khi có kết quả thực tế cho đúng commit.

## Giới hạn

- Đây là service/RPC integration với fixture có trigger; không phải browser → HTTP → production database E2E, không phải bản clone toàn bộ schema/ACL/RLS Live.
- Chỉ chứng minh ba race được chạy; không suy rộng thành mọi pricing advisory lock, payment concurrency hoặc isolation level.
- `test.user_id` và role helpers thuộc fixture local, không phải JWT production. Test ownership logic của RPC không chứng minh deployment ACL/RLS.
- Giữ toàn bộ legacy Pending tests; không xóa khi chưa loại bỏ đầy đủ code, consumer và dữ liệu tương thích.
- Môi trường hiện tại chặn tải `fonts.bunny.net` và Composer advisory endpoint `packagist.org`. Native build/Composer audit phải giữ fail trong CI nếu vẫn gặp lỗi, không thay bằng cấu hình build khác hoặc tắt audit.
- npm advisory GHSA-pqg4-j6r4-53mv được xử lý bằng override riêng `concurrently → shell-quote=1.11.0`; cần bỏ override khi concurrently chính thức dùng phiên bản đã vá. Lockfile do npm tạo, không chỉnh tay. Test security thất bại trước bản vá và đạt sau bản vá.
