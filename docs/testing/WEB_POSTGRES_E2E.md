# Browser → HTTP → PostgreSQL E2E

## Chạy

Yêu cầu: PHP 8.4 với pdo_pgsql, Composer vendor đã cài từ lockfile, Docker local, Bash, Node 22+, Chromium/Chrome, curl, tar, openssl. Không cần tài khoản hay credentials Supabase.

```bash
bash scripts/test-web-e2e.sh
# Chỉ kiểm tra fixture và HTTP startup, chưa chạy browser cases.
bash scripts/test-web-e2e.sh setup
# Có thể chọn executable phù hợp môi trường.
CHROMIUM_BIN=google-chrome PHP_BIN=php bash scripts/test-web-e2e.sh
```

Runner tạo PostgreSQL 17 `laundry_rpc_test` dùng một lần, chỉ publish loopback, nạp fixture hiện có; guard shared kiểm tra host/port/password/database/marker. Tài khoản nhân viên, password, role/permissions và dữ liệu nghiệp vụ chỉ ở fixture local. Runtime /tmp riêng 0700, APP_KEY ổn định mỗi lượt, session file giữ qua request; không nạp `.env` hoặc cached config production. Environment HTTP là `e2e`, không vô hiệu hóa middleware auth/CSRF/quyền. Không có route test-auth trong ứng dụng.

Browser vào trang login thật, submit form bằng email/password seed, dùng cookie session/CSRF tự nhiên. Probes: anonymous request về login, thiếu CSRF trả 419, thiếu quyền reports trả 403. Sau đó tám ca kiểm tra routes/controller/request/service thật và đọc PostgreSQL độc lập qua `tests/E2E/state.php` (CLI only).

| Ca | Phạm vi đã kiểm chứng |
|---|---|
| E2E-01 | GET inspection không ghi; store/store tạo đúng Order, condition, canonical price và giữ estimate |
| E2E-02/03/04 | Ba tổ hợp có nhận/trả tại nhà, exact legs/địa chỉ, lịch trả NULL/lịch nhận hợp lệ |
| E2E-05 | Replay chính POST của browser: cùng Order, một redemption/detail/leg set/audit |
| E2E-06 | Giá readonly trên create/edit Order thật, cả Cái và KG, minimum kg và persisted unit/price; Inspection Booking không có ô giá tự do |
| E2E-07 | Dịch vụ 100.000, voucher 10.000, dùng 90.000 điểm, phí 30.000 vẫn phải trả |
| E2E-08 | Staff sửa paid Order bị financial lock; tạo/sửa GiaoNhan bị service lock, snapshot tiền/chi tiết/invoice/payment/điểm/chặng/audit giữ nguyên. Staff xóa đơn bị owner-only ACL chặn riêng |

Node báo **9 tests** khi tám subtests và parent đều đạt; không cộng thành chín tình huống nghiệp vụ. Bộ PHP/Chromium controls/PostgreSQL service/RPC/races cũ vẫn chạy riêng.

## Assets và giới hạn

- Local production templates/CSS/JS được server phục vụ từ checkout. Không mock HTTP response nghiệp vụ hoặc inject fake Swal/business handlers.
- SweetAlert2 11.26.4 lấy từ official npm tarball, kiểm tra SHA-512 integrity của tarball và SHA-256 script (`tests/E2E/assets.json`); chỉ thay transport của đúng URL @11 hiện có bằng bytes chính thức. Không thêm dependency hoặc sửa script của app. Mismatch/download lỗi làm setup fail; curl retry có giới hạn.
- Với môi trường thiếu mạng, `WEB_E2E_ASSET_ARCHIVE=/absolute/path/sweetalert2-11.26.4.tgz` dùng archive cache đã tải; vẫn kiểm tra đầy đủ hai hash, không chấp nhận script tùy ý.
- Hai CSS trang trí Google Fonts/Font Awesome được loại có chủ ý và URL được ghi trong output; không kiểm chứng typography/icon CDN. Core assets, page exceptions, console error/warning, unexpected external requests và HTTP 5xx làm test fail.
- Không kiểm chứng Supabase Live/schema parity, production JWT/ACL/RLS, Redis/email thật, Flutter hay CDN production. Fixture có role helpers test-only, không giả làm JWT Live.
- HTTP replay không phải HTTP race; ba race PostgreSQL dùng process/lock observations hiện có giữ nguyên. Chưa có payment/pricing race mới.
- E2E-08 không chứng minh owner bị cấm hiệu chỉnh: ORDER_DELETE là owner-only và owner có quyền financial override riêng. Không nới quyền để ép staff vào delete financial guard. Case xóa staff là ACL regression, không là financial-guard coverage.

## Failure và cleanup

Startup/connection/statement/browser command/action/suite có deadlines. Runner cleanup server PID, container ID và runtime do lượt chạy sở hữu trên success/failure/signal; Chromium profile được đóng trong finally. Log server khi fail được lọc bớt request thường và che key/password fixture. Không commit runtime/log chứa credentials.

Nếu probe/test fail, chẩn đoán response và state; không coi HTTP 500/419 hoặc quyền thiếu là financial lock. Native build và dependency audits vẫn nằm trong workflow Verification. Job mới: **Browser HTTP PostgreSQL E2E**, chạy cho PR vào main/push main. Cấu hình required checks trong ruleset là việc riêng, không suy ra từ YAML hoặc Vercel success.
