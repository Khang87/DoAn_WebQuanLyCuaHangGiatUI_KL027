# Cập nhật cuộc trò chuyện đang mở trên web

## Mục tiêu và phạm vi
Tin nhắn được ghi bởi mobile vào `TinNhan` xuất hiện trong cuộc trò chuyện đang mở mà không tải lại trang. Web đang dùng Laravel session; `TinNhan` đã thuộc publication `supabase_realtime`, có RLS cho authenticated. Session Laravel không tự cấp Supabase JWT. Không mở RLS hoặc đưa service_role xuống client.

## Hợp đồng đọc chung
`GET /admin/messages/{order}/updates`, session Laravel, quyền `messages.view`, chặn customer/inactive như trang hiện tại. Trả tối đa 100 tin gần nhất, theo ThoiGianGui rồi TinNhanID; chỉ thuộc order đang yêu cầu. JSON chỉ gồm ID, nội dung, tên hiển thị, is_mine, thời gian hiển thị. Không trả model tài khoản, email, mật khẩu, JWT hay khóa provider. Cache-Control private,no-store. Đơn không tồn tại 404; khách chưa đăng nhập 401 JSON; thiếu quyền 403.

Dùng snapshot có giới hạn thay vì cursor ID đơn thuần để không bỏ sót giao dịch commit muộn, đồng thời phản ánh chỉnh sửa/xóa. Khi client nhận sự kiện hoặc chủ động đồng bộ, đọc qua hợp đồng này và render bằng textContent. Không thay đổi textarea đang soạn; không kéo scroll xuống khi người dùng đang đọc tin cũ; không tạo request chồng nhau; ngừng khi rời trang/tab ẩn; phục hồi khi quay lại.

## Lựa chọn transport
Chủ dự án đã chọn API Laravel tự cập nhật, giữ nguyên session hiện tại. Gọi ngay khi mở trang và sau mỗi 15 giây kể từ khi request trước hoàn tất; timeout 30 giây, lỗi tăng thời gian chờ tới tối đa 120 giây. Tạm dừng khi tab ẩn/offline, đồng bộ khi quay lại. HTTP 401/403/404 dừng đồng bộ và xóa phần tin đang hiển thị. Không tích hợp Supabase Auth hoặc thay đổi RLS.

## Kế hoạch và kiểm chứng
1. Test hợp đồng đọc, giới hạn, thứ tự, tin khác đơn, ACL và thu hồi quyền bằng PHPUnit với SQLite :memory:.
2. Endpoint/controller sử dụng MessageService hiện tại, không đọc lại toàn bộ sidebar/layout.
3. Client riêng cho trang messages, Vite entry; kiểm thử UI tin mới, trùng, XSS, scroll, bản nháp, tab ẩn, lỗi và quyền bị thu hồi.
4. Browser HTTP PostgreSQL fixture: chèn tin mobile vào DB tạm sau khi mở trang, thấy tin mới không reload; không ghi production.

Build: `npm run build`; PHP: `php vendor/bin/phpunit --fail-on-warning --fail-on-risky`; frontend: `node --test tests/Frontend/*.test.mjs`; browser: `bash scripts/test-web-e2e.sh`.
Giữ quy ước Laravel middleware, typed PHP và Pint, SDK hiện có. Không đổi schema/RLS, nghiệp vụ gửi tin hoặc tạo phụ thuộc mới. Việc thay đổi transport được ghi lại trước khi triển khai.
