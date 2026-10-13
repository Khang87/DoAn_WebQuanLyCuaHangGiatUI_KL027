# Báo cáo tổng quan nghiệp vụ — Sky Laundry

## Phạm vi và độ tin cậy

- Rà soát mã nguồn Laravel, routes, services, enums, seeders, cấu hình, tài liệu nghiệp vụ và `schema.sql`.
- Project dùng Laravel `^13.17`, PHP `^8.3` (workspace được cấu hình PHP 8.4), PostgreSQL trên Supabase.
- `schema.sql` là snapshot cấu trúc được ghi nhận ngày **10/10/2026**, không chứa dữ liệu khách hàng. Các tài liệu đồng bộ schema ghi nhận đã đối chiếu metadata Supabase trực tiếp trong đợt đó; báo cáo này không kết nối lại database live nên không khẳng định trạng thái schema hiện tại giống snapshot.
- Không chạy migration, không thực thi DDL và không thay đổi database.
- Có **196 route** trong kết quả `route:list --json` đã thu được ở lượt kiểm kê. Bảng bên dưới nhóm theo chức năng, không phải thống kê số route theo từng module.

## 1. Module và phân quyền

### Module chức năng

| Module | Chức năng chính | Giao diện/API |
|---|---|---|
| Dashboard | Dashboard quản trị/nhân viên, số liệu và biểu đồ doanh thu | Web |
| Khách hàng | Danh sách, hồ sơ và quản lý khách hàng | Web; API `/api/v1/customers` |
| Đặt lịch (Booking) | Xem/sửa/hủy lịch; kiểm tra thực tế, ước tính và tiếp nhận thành đơn | Web; luồng mobile dùng RPC Supabase |
| Đơn hàng | Tạo/sửa đơn, chi tiết dịch vụ, quy trình giặt và chuyển trạng thái | Web; API `/api/v1/orders` |
| Giao nhận | Phiếu nhận đồ/giao đồ, phân công nhân viên và xác nhận tiến độ từng chặng | Web |
| Hóa đơn | Lập, xem, sửa, in, xuất và cập nhật trạng thái hóa đơn | Web; QR chuyển khoản VietQR hiển thị thông tin đối soát |
| Thanh toán | Ghi nhận khoản thu tiền mặt/chuyển khoản, tra cứu và quản lý giao dịch | Web; không thấy webhook ngân hàng trong các luồng đã rà |
| Dịch vụ và đồ giặt | Danh mục dịch vụ, loại đồ giặt, tình trạng đồ | Web; danh mục đọc qua API v1 |
| Bảng giá | Quản lý giá theo dịch vụ/loại đồ/đơn vị | Web |
| Khuyến mãi và coupon | Tạo và quản lý ưu đãi; kiểm tra ưu đãi theo từng luồng tạo đơn | Web; danh sách khuyến mãi API v1/mobile |
| Đánh giá | Xem, phản hồi và ẩn/hiện đánh giá | Web |
| Thông báo | Hộp thư cá nhân, đánh dấu đã đọc và thông báo phát sinh từ nghiệp vụ | Web; Supabase/mobile |
| Tin nhắn | Chat hỗ trợ và hội thoại theo đơn hàng | Web; Supabase Realtime Broadcast và API Laravel dự phòng |
| Tài khoản và RBAC | Tài khoản, vai trò, danh mục quyền và gán quyền | Web |
| Báo cáo/kế toán | Báo cáo tổng hợp, doanh thu và màn hình kế toán | Web |
| Nhật ký hệ thống | Tra cứu dấu vết thay đổi | Web |
| Hồ sơ/đăng nhập | Đăng nhập, đặt lại mật khẩu, đổi mật khẩu, cập nhật hồ sơ/avatar | Web; upload avatar lên Supabase Storage |

### Vai trò và cơ chế cấp quyền

Ứng dụng kiểm tra quyền theo mã capability (`permission:*`) trên từng route; menu/dashboard điều hướng dựa trên quyền được gán, không chỉ dựa vào tên vai trò.

| Vai trò chuẩn trong seeder | Mô tả dự kiến |
|---|---|
| Chủ cửa hàng | Toàn quyền, gồm thao tác với dữ liệu đã quyết toán và hoàn tiền |
| Quản lý | Quản lý vận hành; quyền tài chính hạn chế hơn chủ cửa hàng |
| Nhân viên | Xử lý đơn, giao nhận và khách hàng theo các quyền được cấp |
| Khách hàng | Tài khoản khách; bị chặn khỏi khu vực quản lý web |
| Kế toán | Vai trò bổ sung bởi `RbacCatalogSeeder`, hiện được gán dashboard, báo cáo, xem hóa đơn/thanh toán và kế toán |

`PermissionRegistry` định nghĩa quyền cho các nhóm: đơn hàng, hóa đơn, thanh toán, dịch vụ, khách hàng, giao nhận/đặt lịch, đồ giặt/giá, khuyến mãi, đánh giá/thông báo, báo cáo, tài khoản/phân quyền, tin nhắn, hệ thống, dashboard và kế toán.

**Lưu ý phân quyền:** `RoleAndPermissionSeeder` cũ dùng mã quyền và ma trận quyền kiểu legacy; `RbacCatalogSeeder` dùng registry hiện hành và mapper sang `Quyen.MaQuyen`. Do đó seeder cũ không phản ánh đầy đủ quyền/module hiện tại. Cần kiểm tra dữ liệu `VaiTro_Quyen` đang có trên live trước khi dựa vào các ma trận mặc định; không chạy seeder trên live chỉ để kiểm chứng.

## 2. Quy trình nghiệp vụ

### Booking → kiểm kê → đơn hàng

1. Mobile có thể gửi Booking, các dòng dịch vụ dự kiến, voucher/điểm, phương thức nhận/trả và phí giao hàng qua RPC Supabase; web có màn hình tiếp nhận và kiểm kê Booking.
2. Nhân viên có hồ sơ liên kết và quyền xác nhận kiểm tra lại loại đồ, dịch vụ, khối lượng/số lượng và bảng giá hiện hành.
3. `BookingService::inspectBookingAndCreateOrder` khóa Booking trong transaction, kiểm tra Booking chưa chuyển đổi, lấy hồ sơ nhân viên xác nhận và tạo đơn. Booking chỉ được chuyển `DaXacNhan` sau khi tạo đơn thành công; thao tác lặp trả lại đơn đã tồn tại.
4. Giá đơn được chốt từ bảng giá hiện hành và đồ kiểm kê thực tế; voucher/điểm được xác thực, tiền được tính lại phía server. Phí chặng nhận/trả tại nhà được lấy từ Booking đã lưu khi tạo đơn từ Booking, không tính lại tuyến đường.
5. Tạo đơn sẽ có hóa đơn chưa thanh toán nhờ trigger tương thích trong snapshot. Service `createFromOrder` cũng kiểm tra hóa đơn hiện hữu trước khi tạo để tránh tạo thêm qua luồng ứng dụng.

**Khác biệt giữa các luồng:** tạo đơn trực tiếp trên web không có ô áp voucher theo tài liệu quy tắc nghiệp vụ; request gửi voucher bị từ chối. Đơn chuyển từ Booking có thể giữ voucher hợp lệ đã lưu từ mobile.

### State machine

| Đối tượng | Trạng thái | Quy tắc/chuyển tiếp chính |
|---|---|---|
| Booking | `ChoTiepNhan` → `DaXacNhan` → `HoanThanh`; hoặc `DaHuy` | `DaXacNhan` gắn với luồng tiếp nhận/kiểm kê và tạo đơn, không phải chỉ đổi cột trạng thái. Không cho hủy riêng Booking đã có đơn chưa hủy. |
| Đơn hàng | Chờ tiếp nhận → Đã tiếp nhận → Đang giặt → Hoàn thành giặt → Đang giao → Đã giao; có thể hủy nếu thỏa điều kiện | Không cho bỏ bước/quay ngược ở luồng thông thường. Đơn giao tại cửa hàng có thể hoàn tất không qua phiếu giao tận nhà. Giao tại nhà phải qua phiếu được phân công và hoàn tất. `Đã thanh toán` là giá trị legacy, không phải mốc xử lý giặt. |
| Phiếu giao nhận | Chờ xử lý → đang lấy/giao → Hoàn thành; có thể hủy theo quy tắc từng chặng | Một đơn không được có hai phiếu còn hiệu lực cho cùng một chặng. Chặng nhận và chặng trả tách biệt; trạng thái DB có thể dùng chung giá trị trong khi UI biểu đạt theo loại chặng. |
| Hóa đơn | Chưa thanh toán → Đã thanh toán; hoặc Đã hủy | Hóa đơn chỉ được đánh dấu đã thanh toán khi có giao dịch thành công đủ tiền; hóa đơn đã quyết toán khóa sửa số tiền. |
| Thanh toán | Chờ thanh toán → Thành công / Thất bại; giao dịch thành công có thể thành Đã hoàn tiền | Service hiện yêu cầu số tiền giao dịch phải bằng toàn bộ số phải trả và chặn ghi nhận thu thành công lần thứ hai cho cùng đơn; không phải mô hình thu nhiều lần/trả góp. |

### Thanh toán và VietQR

- Giao diện hỗ trợ tiền mặt và chuyển khoản. Cấu hình VietQR cung cấp tên ngân hàng, tài khoản, chủ tài khoản và quicklink; UI hiển thị số tiền/memo để khách đối chiếu.
- QR không tương đương xác nhận thanh toán: trong các luồng đã kiểm tra không thấy tích hợp webhook ngân hàng/payment gateway tự động đối soát; nhân viên cần ghi nhận giao dịch qua ứng dụng.
- Service kiểm tra đơn/hóa đơn còn hợp lệ, khóa bản ghi trong transaction, kiểm tra đúng số tiền và tình trạng giao dịch trước khi lưu. Đơn/hóa đơn đã hủy hoặc quyết toán bị hạn chế sửa/thu lại.
- Hóa đơn còn dư nợ hiển thị khung QR; QR cũng được giữ trong bản in theo yêu cầu giao diện đã triển khai.

### Giao nhận và phí giao hàng

- Loại chặng là nhận đồ (`NHAN_DO`) hoặc giao đồ sạch (`GIAO_DO`); hình thức là tại cửa hàng hoặc tại nhà.
- Luồng tại nhà yêu cầu địa chỉ và phiếu giao nhận; bước giao cần nhân viên phụ trách. Hoàn tất phiếu giao trả đồng bộ trạng thái đơn.
- `DeliveryFeeService` geocode địa chỉ bằng Nominatim, tìm quãng đường lái xe bằng OSRM. Mốc 3 km đầu miễn phí; phần vượt tính 5.000 đồng/km và làm tròn lên 1.000 đồng. Tọa độ được cache 30 ngày.
- Booking lưu phí/khoảng cách từng chặng; khi tạo đơn từ Booking, dữ liệu đó được sử dụng lại. Hình thức tại cửa hàng không tính phí của chặng tương ứng.

### Điểm tích lũy, khuyến mãi và đánh giá

- Đơn có thể dùng điểm để giảm tiền; điểm/quota khuyến mãi cần được hoàn lại khi hủy theo các rule hiện có.
- Điểm thưởng được tính theo số tiền đơn và gắn nhật ký để chống cộng lặp. Việc cộng điểm trong Laravel chỉ diễn ra khi đơn đã giao và có đúng một khoản thu thành công đủ toàn bộ tiền.
- Trigger legacy trong snapshot cũng tự xử lý điểm và trạng thái khi đơn chuyển `Đã giao`; chi tiết khác biệt với điều kiện của Laravel nằm ở mục Rủi ro.
- Khuyến mãi có thể phát thông báo hàng loạt cho khách đủ điều kiện. Khách hàng đánh giá; nhân viên có quyền phản hồi và bật/tắt hiển thị.

## 3. Thực thể dữ liệu và tự động hóa

### Nhóm bảng chính

| Nhóm | Bảng/thực thể |
|---|---|
| Khách và tài khoản | `KhachHang`, `NhanVien`, `TaiKhoan`, `VaiTro`, `Quyen`, `TaiKhoan_VaiTro`, `VaiTro_Quyen`, `khachhang_diachi` |
| Đặt lịch và đơn | `Booking`, `ChiTietBooking`, `DonHang`, `ChiTietDonHang` |
| Dịch vụ và giá | `DichVu`, `LoaiDichVu`, `LoaiDoGiat`, `DanhMucLoaiDoGiat`, `DonViTinh`, `BangGia` |
| Giao nhận | `GiaoNhan`, `delivery_fee_quotes`, `delivery_fee_quote_attempts` |
| Tài chính | `HoaDon`, `ThanhToan`, `LichSuThayDoiHoaDon`, `DiemTichLuy` |
| Tương tác | `ThongBao`, `TinNhan`, `DanhGia` |
| Quản trị/hạ tầng | `NhatKyHeThong`, `sessions`, `private.web_message_channels` |

Snapshot bao gồm **31 bảng public và 1 bảng private**. Một số bảng quote, địa chỉ và session phục vụ hạ tầng/tính năng, không đồng nghĩa chúng đều có màn hình CRUD.

### Quan hệ nghiệp vụ tiêu biểu

- `Booking` thuộc `KhachHang`, có thể tham chiếu `KhuyenMai`/`NhanVien` và có nhiều `ChiTietBooking`.
- `DonHang` thuộc khách hàng; có thể phát sinh từ một Booking, gắn nhân viên/khuyến mãi và có nhiều `ChiTietDonHang`, `GiaoNhan`, `HoaDon` và `ThanhToan`.
- Dòng chi tiết Booking/đơn hàng gắn dịch vụ, loại đồ giặt và đơn vị tính; giá được đối chiếu với `BangGia`.
- `HoaDon` thuộc `DonHang`; `ThanhToan` gắn trực tiếp `DonHang`; lịch sử hóa đơn trỏ hóa đơn và tài khoản thao tác.
- `TaiKhoan` liên kết hồ sơ nhân viên/khách hàng; vai trò và quyền nối qua hai bảng pivot.
- `DiemTichLuy` gắn khách hàng; thông báo có thể gắn tài khoản và Booking/đơn/chat tùy loại.

### Database routines, triggers và scheduler

Snapshot có **54 routines và 12 triggers**. Các nhóm hành vi tiêu biểu:

| Cơ chế | Tác động nghiệp vụ |
|---|---|
| RPC/mobile | Gửi/xác nhận/hủy Booking; xử lý đơn/trạng thái; thanh toán; chat; đánh giá; hồ sơ/địa chỉ; điểm và thông báo |
| Trigger Booking | Tạo thông báo khi Booking thay đổi/tạo/xóa |
| Trigger đơn hàng | Áp ưu đãi từ Booking, chép phí giao hàng, tạo hóa đơn tương thích, ghi log trạng thái, đồng bộ hủy hóa đơn/phiếu giao nhận và thông báo khách |
| Trigger khuyến mãi | Phát thông báo khi có khuyến mãi đủ điều kiện |
| Trigger tin nhắn | Gửi thông báo khách và broadcast sự kiện thay đổi chat |
| Eloquent observers/services | Ghi lịch sử hóa đơn/đơn; kiểm tra và cập nhật điểm, tiền, trạng thái; transaction/row locking ở các luồng tài chính trọng yếu |
| Laravel scheduler | Mỗi phút xóa thông báo OTP nội bộ quá 10 phút; command `notifications:grant-default` backfill quyền xem inbox |

`schema.sql` chứa cả object tương thích tên/casing legacy và routines không nhất thiết được Laravel gọi trực tiếp. Cần đối chiếu trigger/RPC đang cài trên database live trước khi sửa bất cứ hàm nào.

## 4. Tích hợp bên thứ ba

| Dịch vụ | Vai trò | Mức độ xác minh |
|---|---|---|
| Supabase PostgreSQL | CSDL chính theo schema PascalCase tiếng Việt; RLS/policies, routines và triggers | Cấu hình và tài liệu schema audit có trong repo; snapshot không phải truy vấn live mới |
| Supabase Storage | Lưu avatar; trình duyệt dùng Supabase client/publishable/anon key để upload theo luồng tạo URL và hoàn tất upload | Đã thấy code cấu hình và client |
| Supabase Realtime | Broadcast tín hiệu thay đổi chat; nội dung tải qua API Laravel có kiểm tra session/quyền, polling dự phòng | Đã thấy code và tài liệu; chưa xác minh kết nối WebSocket tới production trong lượt này |
| Resend | Gửi OTP email, gồm luồng đặt lại mật khẩu nội bộ nhân viên | Có mailer/channel và cấu hình; khả năng gửi thực tế phụ thuộc env/provider |
| VietQR | Tạo/hiển thị QR Quicklink và dữ liệu chuyển khoản | Có component và cấu hình; không tìm thấy xác nhận giao dịch tự động qua gateway |
| OpenStreetMap Nominatim + OSRM | Geocoding và ước tính quãng đường giao nhận | Có lời gọi HTTP trực tiếp trong `DeliveryFeeService` |
| Google Maps Routes | Có cấu hình API key/địa chỉ | Chưa thấy service gọi API này; không nên coi là tích hợp đang hoạt động |
| Postmark, SES/AWS, Slack | Có khóa/cấu hình khung dịch vụ Laravel | Chưa đủ bằng chứng từ lượt rà soát để xác nhận được sử dụng trong nghiệp vụ hiện tại |

Không thấy bằng chứng về SMS gateway hoặc một payment gateway có callback đối soát trong các luồng đã rà soát.

## 5. Điểm nghẽn, edge cases và rủi ro

| Mức độ | Vấn đề | Tác động/khuyến nghị |
|---|---|---|
| Cao | **Điểm thưởng có thể được cấp trước khi thanh toán đủ.** `OrderService::awardPointsForSettledDeliveredOrder` yêu cầu đơn đã giao và khoản thu thành công đủ tiền, nhưng `private.record_legacy_order_status_change` trong snapshot cộng điểm ngay khi trạng thái thành `Đã giao`, không kiểm tra số tiền thu trước bước cộng điểm. Cùng tên action trong nhật ký khiến Laravel có thể coi điểm đã được cộng và bỏ qua lần kiểm tra sau. | Đối chiếu trigger đang chạy và dữ liệu điểm trên live; thống nhất điều kiện trao điểm tại một nguồn sự thật. Không sửa trigger/snapshot bằng cách chạy DDL trực tiếp. |
| Cao | **Giá trị trạng thái legacy `Đã thanh toán` cạnh tranh với trạng thái vận hành.** Trigger snapshot có thể đổi `DonHang.TrangThai` sang `Đã thanh toán` sau khi phát hiện đã thu đủ tiền; enum Laravel lại ghi rõ đây là giá trị legacy, không phải mốc xử lý. | Có thể làm trạng thái giặt/giao bị thay thế hoặc hiển thị sai nếu trigger còn hoạt động; xác minh thứ tự trigger và dữ liệu live, thống nhất lưu trạng thái thanh toán ở hóa đơn/giao dịch. |
| Trung bình | **Chỉ hỗ trợ một khoản thu đủ tiền cho đơn.** Service chặn khoản thu thành công thứ hai và bắt số tiền bằng toàn bộ dư phải trả. | Không hỗ trợ đặt cọc/thu nhiều đợt; nếu cửa hàng cần nghiệp vụ này phải thiết kế lại invariant, tổng dư nợ và đối soát trước khi đổi giao diện. |
| Trung bình | **QR là hướng dẫn chuyển khoản, không phải xác nhận thanh toán.** Ảnh/Quicklink không tự chứng minh tiền đã về tài khoản. | Nhân viên cần kiểm tra giao dịch ngoài hệ thống rồi ghi nhận; tránh để khách hiểu quét QR đồng nghĩa đơn đã thanh toán. |
| Trung bình | **Hai bộ seeder quyền không cùng vocabulary/độ bao phủ.** Seeder legacy có thể tạo ma trận thiếu/sai quyền so với `PermissionRegistry` và catalog hiện tại. | Chỉ sử dụng quy trình seed được kiểm soát; rà lại vai trò/quyền live trước khi đồng bộ, tránh chạy seeder có thao tác xóa pivot trong môi trường thật. |
| Trung bình | **Phụ thuộc dịch vụ định tuyến công cộng.** Nominatim/OSRM có timeout, rate limit và dữ liệu địa chỉ không chính xác; phí có thể không tính được khi provider lỗi. | Cần theo dõi lỗi/provider và có quy trình vận hành khi quote thất bại; không dùng phí từ client làm nguồn tin cậy. |
| Trung bình | **Hành vi bị chia giữa Laravel và Supabase.** RPC, trigger, observer và service cùng tác động thông báo, trạng thái, hóa đơn và điểm. | Thay đổi một luồng cần kiểm thử cả web/mobile và database; snapshot/schema parity CI quan trọng để phát hiện drift. |
| Thấp | **Snapshot và cấu hình triển khai có thể lệch thời điểm.** Tài liệu schema xác nhận một số bảng quote/mobile và tương thích được bổ sung trên live theo các đợt khác nhau. | Trước phát hành, so sánh schema catalog hiện tại với `schema.sql`; không chạy snapshot full restore như migration. |
| Cần xác minh | **Deployment retention và giới hạn bundle không thể kết luận từ code Laravel.** Đây là chính sách/cấu hình project Vercel ngoài repository. | Kiểm tra trong Vercel Dashboard/API riêng; báo cáo này không xác nhận cấu hình retention hiện hành. |

## Tài liệu và mã nguồn đối chiếu

- [PermissionRegistry](./app/Support/PermissionRegistry.php), [RoleAndPermissionSeeder](./database/seeders/RoleAndPermissionSeeder.php), [RbacCatalogSeeder](./database/seeders/RbacCatalogSeeder.php)
- [Routes web](./routes/web.php), [Routes API](./routes/api.php), [Scheduler](./routes/console.php)
- [OrderService](./app/Services/OrderService.php), [BookingService](./app/Services/BookingService.php), [PaymentService](./app/Services/PaymentService.php), [DeliveryService](./app/Services/DeliveryService.php), [InvoiceService](./app/Services/InvoiceService.php)
- [Enums trạng thái](./app/Enums/), [Permission registry](./app/Support/PermissionRegistry.php)
- [Supabase schema snapshot](./schema.sql), [Schema sync report](./docs/testing/SUPABASE_SCHEMA_SYNC.md), [Business rules](./docs/supabase/BUSINESS_RULES.md), [Schema audit](./docs/supabase/SCHEMA_AUDIT.md)

