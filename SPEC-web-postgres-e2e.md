# Spec: web-postgres-e2e

Status: **Draft — chưa được duyệt, chưa implementation.** Ngày 08/10/2026. Một capability: kiểm chứng browser → Laravel HTTP → PostgreSQL xuyên suốt. Nối tiếp PR #8, nơi browser E2E được loại khỏi phạm vi; không mở lại các thay đổi nghiệp vụ đã chốt.

## Objective and assumptions

Đóng khoảng trống mục 12 của BUG ĐÁNG YÊU VER2 bằng tám ca tự động đi qua browser thật, đăng nhập/session/CSRF thật, routes/controller/request/service hiện tại và kiểm tra dữ liệu PostgreSQL đã lưu. Giả định giữ giá canonical, kiểm kê bắt buộc, quyền hiện tại và tương thích legacy. PostgreSQL 17 cục bộ dùng một lần là đích kiểm thử; không kết nối Supabase Live. Không cần mã Flutter hoặc JWT Supabase cho các ca Web này.

## Stack and commands

Laravel 13 / PHP 8.4, Node 22+, Chromium, Docker PostgreSQL 17; Composer/npm lockfile hiện có. Ưu tiên CDP/Node built-ins đã dùng trong tests/Frontend; không thêm package. Những lệnh mới dưới đây là contract đề xuất, **chưa tồn tại**:

```bash
# Create fixture, start isolated HTTP app, run browser scenarios, verify DB, clean up.
bash scripts/test-web-e2e.sh
# Existing required regressions remain unchanged.
bash scripts/test-postgres.sh
php vendor/bin/phpunit --fail-on-warning --fail-on-risky
node --test tests/Frontend/*.test.mjs
php artisan view:cache
npm run build
```

Runner chấp nhận `PHP_BIN` và `CHROMIUM_BIN` cho executable local. Chạy sai cấu hình/missing runtime phải trả nonzero rõ ràng. Chỉ thay CI sau khi đặc tả và kế hoạch được duyệt; thêm bước E2E vẫn giữ các gate hiện tại.

## Project structure

- `scripts/test-web-e2e.sh`: điều phối container PostgreSQL dùng một lần, fixture, HTTP loopback, browser và cleanup; tái sử dụng safety contract của runner hiện có.
- `tests/E2E/`: Node browser scenarios, PHP seed/DB assertions và HTTP bootstrap test riêng. Các helper nằm trong test layer, không thêm route đăng nhập bỏ qua vào app.
- `tests/Postgres/fixtures.sql`: tái sử dụng và chỉ bổ sung schema local tối thiểu khi full HTTP cần quan hệ/quyền còn thiếu; không migration production.
- `docs/testing/WEB_POSTGRES_E2E.md`: prerequisites, lệnh, phạm vi và cách đọc lỗi.
- `TESTCASES.md`: thêm tám ca với STT liên tục, giữ cả nhóm current/legacy; README và tasks/verification ghi đúng kết quả.

## Code style

PHP theo Pint và namespace hiện có; Node theo node:test/assert nghiêm ngặt, đặt tên theo hành vi. Ví dụ tiêu chí persisted outcome, không nhân bản thuật toán tính tiền:

```js
assert.equal(savedOrder.total, 30000, 'delivery fees remain payable after voucher and points');
```

Browser test đọc dữ liệu qua helper test trong process local có safety guard, không xuất credentials hay thêm API test vào production. Không đưa số liệu triển khai vào UI của sản phẩm.

## Acceptance scenarios

| ID | Browser action | Persisted outcome |
|---|---|---|
| E2E-01 | Đăng nhập nhân viên có quyền, mở inspection Booking nhận/trả tại cửa hàng, nhập tình trạng/measurement, xác nhận | GET không tạo đơn; POST tạo một Order Đã tiếp nhận, actual condition và giá theo đủ ba khóa; estimate Booking giữ nguyên; không tạo chặng |
| E2E-02 | Nhận tại nhà, trả cửa hàng | Chỉ NHAN_DO, lịch nhận hợp lệ |
| E2E-03 | Nhận cửa hàng, trả tại nhà | Chỉ GIAO_DO; lịch return được NULL khi mới tiếp nhận |
| E2E-04 | Nhận/trả tại nhà | Một phiếu mỗi chiều, giữ địa chỉ riêng, phí hai chiều đúng |
| E2E-05 | Gửi lại thao tác xác nhận cùng Booking qua browser/session thật | Trả/link tới một Order; một lần redemption, một bộ chi tiết/chặng và một audit; đây là replay HTTP, không thay thế ba race service tests đã có |
| E2E-06 | Cùng Service/Garment có Cái và KG; đổi ĐVT/measurement trên form hiện hành | Lưu đúng ĐVT/giá và minimum kg; không có ô giá cho chỉnh tự do trên Inspection Booking; nếu UI tương lai có preview thì phải readonly; form create/edit Order readonly hiện có được kiểm tra trên page thật |
| E2E-07 | Dịch vụ 100.000, voucher 10.000, chọn dùng điểm đủ 90.000, phí 30.000 | Điểm trừ đúng một lần, phí không bị giảm, còn phải trả 30.000; UI và dữ liệu lưu nhất quán |
| E2E-08 | Với đơn Đã thanh toán, thử gửi các thao tác sửa/xóa/tạo hoặc sửa GiaoNhan qua HTTP có quyền | Server từ chối cả khi UI ẩn nút; snapshot tiền/chi tiết/điểm/chặng không đổi; kiểm tra response theo contract hiện tại, không chấp nhận 500 như một từ chối hợp lệ |

## Testing strategy

- Full HTTP đăng nhập tài khoản seed cô lập, dùng cookie session và CSRF thật; không `withoutMiddleware`, không fake actor hoặc tắt authorization để gọi route.
- Seed catalog/employee/permission/booking/voucher/points local sau xác minh host/database/marker. Browser thực hiện nghiệp vụ; DB helper chỉ chuẩn bị fixture và đọc assertions sau thao tác.
- Chứng minh test đi qua route thật bằng kiểm tra request/response và persisted state, không chỉ DOM text hay gọi service thay browser.
- Page assets cần thiết phục vụ từ checkout/build local. Không dùng network mocking để làm giả response nghiệp vụ, không bỏ lỗi HTTP/JS trọng yếu; assets ngoài mạng phải được nhận diện trong bước plan.
- Mỗi lượt có profile, HTTP runtime/cache/storage/key, container/password riêng. Timeouts cho startup, browser action, DB statement và toàn bộ suite; cleanup sở hữu tài nguyên theo ID, kể cả thất bại.
- Mutation trên bản sao cách ly bỏ một guard covered phải khiến ca liên quan fail; restore rồi rerun. Không làm mutation trên production hay commit bản phá guard.
- Run existing PHP/Chromium/PostgreSQL regression, Blade, native build và audits trước merge; ghi SHA/runtime/kết quả thực tế. Dữ liệu fixture không chứng minh schema, JWT, ACL/RLS hoặc CDN của production.

## Boundaries

Always: isolation/safety marker trước seed; dùng auth/CSRF/quyền thực; giữ kiểm thử cũ; fail nonzero; cập nhật TESTCASES và docs đúng mức kiểm chứng.

Ask first: duyệt đặc tả này, rồi kế hoạch/task theo skill; thay đổi business policy, dependency/schema production hoặc repository protection nằm ngoài phạm vi này.

Never: load `.env` hoặc cache config production; chạy fixture/test mutations trên Live; tạo auth bypass/route test trong app; xóa legacy; cho override giá Booking; thêm redelivery; tắt gate để báo xanh; gọi local E2E là Supabase Live E2E.

## Open questions

Không có câu hỏi về quy tắc nghiệp vụ. Cần chủ dự án duyệt phạm vi E2E PostgreSQL cách ly này. Khả năng boot full HTTP trên fixture hiện có, assets và seed quyền sẽ được khảo sát trong Phase 2 Plan; nếu phát hiện cần đổi business contract hoặc thêm dependency thì phải quay lại đặc tả.
