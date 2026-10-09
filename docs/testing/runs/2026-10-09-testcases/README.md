# Kết quả thực thi TESTCASES — 09/10/2026

Mã nguồn đối chiếu: `b46c41824c5de4f46536084871677227f26a5e3c` (main sau PR #14). Lượt chạy local ngày 09/10/2026, GMT+7 (`Asia/Ho_Chi_Minh`). Không đọc `.env`, không ghi dữ liệu thử lên production, không sửa mã nghiệp vụ.

Đối chiếu 335 dòng với assertions thực sự đã chạy; số dòng tài liệu không bằng số test của runner. `Passed` ghi nhận hành vi tự động được chứng minh bằng fixture ở test nguồn; dữ liệu ví dụ trong bảng được thay bằng fixture tương đương ở test, số tiền quan sát được ghi ở Actual Result. Đây không phải 335 lượt thao tác thủ công trên production. `Pending` gồm cả ca chỉ kiểm một phần; trường Actual Result ghi phần còn thiếu. Mỗi biến thể chưa có bằng chứng không được suy ra từ việc một suite xanh.

| Nhóm | Passed | Pending | Blocked | Failed | Tổng |
| --- | ---: | ---: | ---: | ---: | ---: |
| Luồng hiện tại | 241 | 38 | 3 | 0 | 282 |
| Legacy | 23 | 6 | 0 | 0 | 29 |
| Admin-triggered OTP | 24 | 0 | 0 | 0 | 24 |
| **Tổng** | **288** | **44** | **3** | **0** | **335** |

| Bộ chạy | Kết quả quan sát | Bằng chứng |
| --- | --- | --- |
| PHPUnit strict, baseline | 338 tests / 1.757 assertions; 0 failure/skip/warning/risky | [JUnit](phpunit.xml), [stdout](phpunit.txt) |
| PHPUnit strict, harness bổ sung | 42 tests / 183 assertions; 0 failure/skip/warning/risky | [JUnit](supplemental.xml), [stdout](supplemental.txt), [patch](supplemental.patch) |
| Frontend Node/Chromium | 2 tests đạt, gồm pricing DOM và shell-quote security | [TAP](frontend.txt) |
| PostgreSQL 17 | 4 guard cấu hình; RPC assertions; 66 Web/RPC assertions; 3 race thật / 21 assertions đạt | [stdout](postgres.txt) |
| Browser baseline | 9 subtests nghiệp vụ/UI + parent = 10 Node tests đạt | [TAP](browser.txt) |
| Browser bổ sung Settings | 9 subtests cũ + 3 Settings + parent = 13 Node tests đạt | [TAP](browser-supplemental-final.txt), [patch](supplemental-browser.patch) |
| Profile hiệu năng HTTP/browser | 1 test đạt; orders 10, create 16, staff 13 queries; login/menu/logout/cancel với Swal delay 500ms | [log/samples](performance.txt) |

[Manifest từng ca](case-results.json) giữ STT, ID, trạng thái, hành vi quan sát và tên chính xác phương thức PHPUnit/dataset. Với PostgreSQL/browser, đối chiếu runner và nhãn assertion trong log; nguồn test hiện hành đã giữ ở cột truy vết của TESTCASES.md.

## Phạm vi và giới hạn

- SQLite in-memory chứng minh logic/rollback trong fixture, không chứng minh khóa đồng thời. RPC/race được chạy riêng trên PostgreSQL 17 loopback, marker cô lập và container do runner sở hữu.
- Email dùng Resend mock, cache array; không chứng nhận deliverability hoặc Redis production. Test scheduler gọi callback thực sự và kiểm hàng DB bị dọn, không chứng nhận cron production đang chạy.
- Assets SweetAlert2 official được pin/hash; CSS font/icon ngoài bị loại có chủ ý. Không có kết luận CDN production hay Web Vitals thực địa.
- TC-SET-03 lần đầu trả 500 do bản checkout scratch loại `public/build`: [log chẩn đoán](preparation-diagnostic.txt) xác định `Vite manifest not found`. Sau nạp build đầy đủ, GET profile trả 200 và assertion không có link Settings đạt; không sửa app hoặc nới assertion. Hai lỗi chuẩn bị harness khác (helper tên không tồn tại, nhãn enum/header Accept sai) được sửa theo mã nguồn, không sửa nghiệp vụ.
- TC-PROD-AVATAR-01/02 và đối chiếu catalog Live bị Blocked: proxy từ chối CONNECT 403 khi thử GET login; không có chứng cứ live catalog/avatar. Đây là giới hạn lượt chạy, không phải lỗi sản phẩm.
- Legacy vẫn cần hồi quy: không có kiểm kê dữ liệu production để chứng minh có thể xóa. Những biến thể thiếu (ví dụ rollback lỗi ghi điểm legacy, ACL HTTP legacy) vẫn Pending với lý do cụ thể.

## Hai kỳ vọng tài liệu được sửa

| STT / ID | Kỳ vọng cũ | Kỳ vọng đúng theo code và assertion đã chạy |
| --- | --- | --- |
| 146 / TC-REG-16 | Resource loại đồ kết hợp role middleware và permission | Resource dùng permission theo hành động; không có role middleware cố định. |
| 203 / TC-COMM-06 | Nhật ký yêu cầu hai role middleware quản trị/Chủ cửa hàng | Route dùng `permission:system_logs.view`; owner-only được xử lý theo permission/mapper hiện hành, không dùng `role:manager` hay `role:admin` cố định. |

## Tái thực thi

Trên checkout cùng SHA, cài dependencies bằng lockfile, PHP 8.4 với PDO SQLite/PostgreSQL, Node/Chromium và Docker. Không trỏ test vào DB Live. PHPUnit dùng cấu hình SQLite in-memory; cache/session/mail cô lập. Dùng bản checkout test riêng để áp dụng các patch harness; patch chỉ thêm assertion vào test, không áp dụng vào app.

```bash
php vendor/bin/phpunit --fail-on-warning --fail-on-risky --log-junit /tmp/baseline.xml
node --test tests/Frontend/*.test.mjs
PHP_BIN=php bash scripts/test-postgres.sh
npm run build
PHP_BIN=php bash scripts/test-web-e2e.sh
PHP_BIN=php bash scripts/test-web-e2e.sh performance
# Trong checkout test riêng cùng SHA:
git apply docs/testing/runs/2026-10-09-testcases/supplemental.patch
php vendor/bin/phpunit --fail-on-warning --fail-on-risky --filter test_documented_ --log-junit /tmp/supplemental.xml
git apply docs/testing/runs/2026-10-09-testcases/supplemental-browser.patch
PHP_BIN=php bash scripts/test-web-e2e.sh
```

Lượt này PHP chạy trong image `laundry-verification-php:8.4` vì host không có PHP; PHPUnit container tắt mạng, source read-only, `.env` bị loại, APP_KEY thử nghiệm sinh riêng. Wrapper cho PostgreSQL/HTTP chỉ dùng network container/loopback do runner test tạo. `WEB_E2E_ASSET_ARCHIVE` trỏ bản archive SweetAlert2 official đã kiểm hash. Các lệnh tương đương ở trên dùng PHP đã cài trực tiếp. Log chỉ chứa fixture test, không có OTP/email/thông tin đăng nhập production.
