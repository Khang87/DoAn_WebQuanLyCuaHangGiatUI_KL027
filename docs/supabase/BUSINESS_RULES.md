# Quy tắc chung của Web và App

Chốt ngày 07/10/2026 với chủ dự án: **1 điểm = 1đ; chỉ trừ khi tạo đơn sau kiểm kê; giá được chốt theo bảng giá hiệu lực lúc kiểm kê**. Dữ liệu nghiệp vụ nằm trong PostgreSQL Supabase QLGiatUi (`osnblefzulmsuthhuswl`). Tài liệu này là hợp đồng nghiệp vụ; Laravel và RPC phải được kiểm thử cùng hợp đồng khi thay đổi.

## Các đường ghi dữ liệu

| Nghiệp vụ | Web | RPC / PostgreSQL |
|---|---|---|
| Ước tính đặt lịch | BookingService → PricingService | submit_laundry_order_cart; endpoint một dòng gọi chung cart |
| Kiểm kê và tạo đơn | BookingService::inspectBookingAndCreateOrder → OrderService::createFromBooking | Hai confirm RPC cũ chỉ trả đơn đã tồn tại; booking mới phải qua kiểm kê Web |
| Giá hiệu lực | PricingService (một dòng / nhiều tổ hợp) | Cart chọn cùng bộ ba và cùng thứ tự hiệu lực |
| Chuyển trạng thái | OrderStatus::canTransitionTo + OrderService::assertStatusTransition | transition_laundry_order |
| Tiền thu | PaymentService; khóa đơn trước khoản thu | request_order_payment / confirm_order_payment; khóa cùng thứ tự |
| Cộng / hoàn điểm | OrderObserver trên SQLite; marker tránh làm lại trên PostgreSQL | Trigger hiện có record_legacy_order_status_change dùng cùng marker |
| Hủy đặt lịch | BookingService; trả khoản giữ chỗ cũ | cancel_laundry_booking; idempotent |

Không có mã nguồn Flutter trong repository Web. Chữ ký và kiểu trả về của các RPC được giữ nguyên. RPC xác nhận chỉ có `p_bookingid`, không có khối lượng thực tế hay tình trạng đồ: dùng nó để tạo đơn từ ước tính sẽ vi phạm kiểm kê. Với booking mới, RPC trả lỗi `22023` kèm hướng dẫn dùng màn hình kiểm kê Web; gọi lại sau khi Web tạo đơn trả ID đơn hiện có. Muốn kiểm kê trực tiếp trên Flutter về sau cần thiết kế API nhận đủ dữ liệu và sửa UI Flutter, không dùng chi tiết dự kiến làm bằng chứng kiểm kê.

## Đơn tạo trực tiếp trên Web

Đơn khách mang đồ trực tiếp được tạo qua `POST /orders` chỉ sau khi nhập ít nhất một dòng kiểm kê thực tế, số lượng/khối lượng hợp lệ và `TinhTrangTruocKhiGiat` không rỗng (tối đa 320 ký tự). Trạng thái khởi tạo luôn là **Đã tiếp nhận**; không cho khởi tạo Chờ tiếp nhận, Đang giặt, Đã giao hoặc Đã thanh toán. Các trạng thái tiếp theo phải đi qua chuyển trạng thái/thanh toán hiện có.

Không nhận `BookingID` trên đường tạo trực tiếp: Booking phải qua `inspectBookingAndCreateOrder()` để cập nhật người xác nhận, điểm, giao nhận và audit trong cùng transaction. Request kiểm tra trước controller; `OrderService::create()` cũng bảo vệ hợp đồng khi gọi trực tiếp. Dữ liệu đơn Pending lịch sử tiếp tục dùng `completeReceivingInspection()`.

Validation dùng Form Request và `prohibited` theo tài liệu Laravel 13: https://laravel.com/docs/13.x/validation#form-request-validation và https://laravel.com/docs/13.x/validation#rule-prohibited. Lỗi nhập liệu theo cơ chế Laravel hiện có (session errors cho form; HTTP 422 cho yêu cầu JSON).

## Giá và tổng tiền

- Khóa tra giá: `DichVuID + LoaiDoGiatID + DonViTinhID`.
- `TrangThai = Hoạt động`; ngày bắt đầu không sau hôm nay; ngày kết thúc trống hoặc không trước hôm nay. Hai biên đều bao gồm ngày đó.
- Ưu tiên ngày bắt đầu mới nhất, tiếp theo `BangGiaID` lớn nhất. Ngày bắt đầu NULL chỉ là fallback cho dữ liệu cũ.
- Giá ID từ App xác định tổ hợp, không buộc dùng giá cũ hoặc giá tương lai. Giá đặt lịch là ước tính; các dòng đơn giữ snapshot giá lúc kiểm kê. Luồng kiểm nhận đơn Pending lịch sử giữ các snapshot đơn đã có.
- KG (`kg`, `kgs`, `kilogram`): khối lượng làm tròn hai chữ số; tiền dòng = `round(max(kg, 3) × đơn giá, 0)`. Món: số lượng nguyên dương, tiền dòng = `round(số lượng × đơn giá, 0)`.
- `khối_lượng_tối_thiểu` Web hiện là 3kg. Khi đổi cấu hình này, phải cập nhật RPC và cả kiểm thử PostgreSQL trong cùng PR.
- Giảm khuyến mãi trước, giảm điểm sau; mỗi khoản chặn theo tiền dịch vụ còn lại. Phí giao nhận cộng sau giảm giá, không dùng điểm để giảm phí.
- `ThanhTien = max(TongTien - TienGiamKhuyenMai - TienGiamDoDiem, 0) + PhiGiaoHang`.
- Phí hai chặng lấy từ `Booking.PickupDeliveryFee + Booking.DeliveryFee`; đây là cột đã có trên Live, không tạo mới. RPC báo phí vẫn dùng quote hiện hữu.
- Voucher đã giữ cho booking được kiểm tra lại khi kiểm kê; hết hiệu lực, không đạt tối thiểu hoặc vi phạm điều kiện đơn đầu tiên thì trả lượt giữ chỗ một lần và loại voucher.

## Điểm

- Đặt lịch App lưu ý định dùng điểm trong các cột sẵn có, `DiemDaTru=false`; không sửa số dư điểm.
- Kiểm kê Web cho nhân viên xác nhận lại việc dùng điểm. Điểm trừ = min(điểm được chọn, số dư hiện tại, phần tiền dịch vụ còn lại sau khuyến mãi), 1 điểm = 1đ.
- Booking cũ có `DiemDaTru=true`: hoàn khoản giữ chỗ trong cùng transaction trước khi trừ điểm cho đơn thực tế. Chuyển đổi thành công xóa dấu giữ chỗ trên booking; lỗi bất kỳ rollback cả số dư lẫn đơn.
- Khi hủy/xóa booking chưa có đơn, chỉ trả điểm đã thực sự giữ (`DiemDaTru=true`), không trả ý định dùng điểm chưa trừ. Cờ giữ voucher cũng được xóa sau khi trả lượt.
- Khi đơn được giao: `floor(ThanhTien / 1000) × 100` điểm. Marker audit `Cộng điểm tích lũy đơn hàng` ngăn cộng lặp giữa Web và App.
- Hủy đơn trước khi giặt hoàn điểm đã dùng một lần, marker `Hoàn điểm tích lũy đơn hàng`. Xóa đơn đã hủy không hoàn thêm lần nữa. Hóa đơn và phiếu giao nhận của đơn được chuyển sang Đã hủy.
- Không tự sửa hàng loạt số dư hay booking lịch sử trên Live. Các đơn lịch sử đã tạo bằng quy trình cũ cần đối soát riêng khi phát hiện sai lệch.

## Trạng thái và thanh toán

| Trạng thái hiện tại | Đích hợp lệ |
|---|---|
| Chờ tiếp nhận (đơn lịch sử) | Đã tiếp nhận chỉ qua kiểm nhận; hoặc Đã hủy |
| Đã tiếp nhận | Đang giặt; Đã hủy |
| Đang giặt | Hoàn thành giặt |
| Hoàn thành giặt | Đang giao; Đã giao tại cửa hàng |
| Đang giao | Đã giao |
| Đã giao | Đã thanh toán khi đã thu đủ |
| Đã thanh toán / Đã hủy | Không chuyển tiếp trong quy trình thường |

Gọi lặp một trạng thái không tạo thêm lần cộng/hoàn điểm. Hủy đơn mới cần lý do; đơn có khoản thu Thành công cần xử lý hoàn tiền trước khi hủy. Owner override không cho bỏ bước giặt; ngoại lệ mở lại Đã thanh toán → Đã giao chỉ phục vụ chỉnh khoản thu của chủ cửa hàng khi số tiền đã thu thực tế thấp hơn tổng phải thu. App không có tham số override tài chính này.

Nhân viên Web có thể ghi nhận tiền trả trước cho đơn đã tiếp nhận. Khách hàng App chỉ yêu cầu thanh toán sau khi giao; đây là khác biệt theo tác nhân, không phải hai quy tắc quyết toán. Cả hai không được thu cho đơn hủy, không vượt số tiền còn phải thu, không đánh dấu đơn quyết toán trước khi giao và thu đủ. Chỉ khoản thu `Thành công` được cộng vào tổng đã thu.

Live `ThanhToan` **không có `IdempotencyKey`**. RPC thanh toán sử dụng `MaGiaoDich = RPC-<UUID>` để chống yêu cầu lặp, không tạo cột. Xác nhận cùng kết quả trả lại thành công mà không thu thêm; xác nhận ngược kết quả của khoản đã xử lý bị từ chối. Web cấm sửa khoản đã thu nếu không có quyền hiệu chỉnh tài chính.

## SQL và kiểm thử

`rpc-business-rules.sql` chỉ thay thế **13 hàm đã có**, giữ nguyên chữ ký/kiểu trả về/owner/ACL. Guard kiểm tra hàm đã tồn tại trước khi `CREATE OR REPLACE`. Không có CREATE TABLE, ALTER TABLE, DROP COLUMN hoặc migration history. `rpc-business-rules.rollback.sql` lưu định nghĩa trước thay đổi; rollback toàn bộ cùng bản Web tương ứng, không khôi phục riêng RPC trừ điểm đặt lịch khi Web mới vẫn đang chạy.

`tests/Postgres/fixtures.sql` là **fixture cục bộ**, khác với script cập nhật Live: nó tạo bảng giả chỉ khi database tên `laundry_rpc_test`. Bộ PostgreSQL không dùng dữ liệu/tài khoản Live. Ví dụ trong container PostgreSQL cục bộ, mount repository vào `/source`:

```sh
createdb -U postgres laundry_rpc_test
psql -U postgres -d laundry_rpc_test -v ON_ERROR_STOP=1 -f /source/tests/Postgres/fixtures.sql
psql -U postgres -d laundry_rpc_test -v ON_ERROR_STOP=1 -f /source/tests/Postgres/business_rules.sql
```

Bộ PHP: `php artisan test --compact`. PHPUnit buộc SQLite `:memory:` và DB_URL rỗng; cấu hình sai phải fail, không tạo skipped giả. Kết quả hai bộ độc lập không gộp số ASSERT PostgreSQL thành số test PHPUnit. Báo cáo 192 case cũ: `docs/testing/LEGACY_TESTS.md`.
