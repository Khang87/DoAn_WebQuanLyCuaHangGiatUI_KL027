# Booking → đơn hàng sau kiểm kê

## Mục tiêu và phạm vi

Booking từ mobile là yêu cầu đặt lịch, chưa phải đơn hàng. Nhân viên xác nhận hoặc hủy Booking mà không sửa chi tiết trong thao tác xác nhận. Khi xác nhận, hệ thống lập hai chứng từ gắn Booking: chặng nhận và chặng trả, kể cả khi chặng diễn ra tại cửa hàng. `DonHang` chỉ được tạo sau kiểm kê. Nếu số lượng, loại đồ, dịch vụ hoặc tình trạng thực tế khác khai báo, khách hàng phải xác nhận đúng phiên bản kiểm kê trên mobile trước khi tạo đơn.

## Bản đồ năng lực

| Mã | Trách nhiệm | Phụ thuộc |
|---|---|---|
| booking-approval | Chuyển `ChoTiepNhan` → `DaXacNhan` hoặc `DaHuy`, ghi người/thời điểm xác nhận | Booking hiện tại |
| booking-legs | Lập hai phiếu dự kiến gắn Booking, giữ loại/hình thức/địa chỉ/phí của từng chặng | booking-approval |
| booking-inspection | Lưu bản kiểm kê thực tế, so sánh dữ liệu khách khai báo và khóa phiên bản chờ duyệt | booking-approval |
| customer-ack | Khách chỉ đọc bản kiểm kê của mình và xác nhận phiên bản hiện tại qua Supabase RLS | booking-inspection |
| order-conversion | Chỉ tạo một đơn từ Booking đã xác nhận và bản kiểm kê hợp lệ; kế thừa voucher, điểm, phí | booking-legs, customer-ack |

Thứ tự: booking-approval → booking-legs → booking-inspection → customer-ack → order-conversion.

## Hợp đồng trạng thái

| Trạng thái | Đơn hàng | Thao tác tiếp theo |
|---|---|---|
| Booking `ChoTiepNhan` | Không có | Xác nhận hoặc hủy |
| Booking `DaXacNhan`, chưa kiểm kê | Không có | Nhận đồ, kiểm kê |
| Kiểm kê không chênh lệch | Chưa có đến khi nhân viên chốt | Tạo đơn từ bản kiểm kê |
| Kiểm kê chênh lệch, chờ khách xác nhận | Không có | Khách xem và xác nhận trên mobile |
| Khách xác nhận đúng phiên bản kiểm kê | Chưa có đến khi nhân viên chốt | Tạo đơn; chặn bản cũ/đã thay đổi |
| Booking `DaHuy` | Không có | Không tạo đơn |

Chứng từ chặng Booking được lưu riêng vì `GiaoNhan.DonHangID` hiện bắt buộc. Khi có đơn, các chặng tại nhà được sao chép sang `GiaoNhan` theo hợp đồng đơn hiện tại; chứng từ Booking vẫn là lịch sử trước đơn.

## Giao diện và bảo mật

- Trang chi tiết Booking có hai thao tác riêng: xác nhận hoặc hủy; form kiểm kê chỉ mở sau xác nhận.
- Form kiểm kê cho nhập số lượng, khối lượng, tình trạng thực tế và đánh dấu chênh lệch chất lượng. Mã Booking, khách hàng, ưu đãi và phí đã lưu chỉ đọc.
- Thông báo đến đúng tài khoản khách khi có bản kiểm kê cần duyệt. Mobile chỉ được đọc và xác nhận bản kiểm kê thuộc `KhachHangID` của phiên Supabase hiện tại; không được sửa bản kiểm kê hoặc xác nhận Booking khác.
- Laravel kiểm tra lại trạng thái, phiên bản đã duyệt và dữ liệu tài chính trong giao dịch khóa hàng trước khi tạo đơn. Tạo đơn lặp phải trả cùng một đơn.

## Cấu trúc, mã và kiểm thử

- Laravel: `app/Services/BookingService.php`, `app/Services/OrderService.php`, `app/Http/Controllers/Admin/BookingController.php`, Blade trong `resources/views/admin/bookings/`.
- Cơ sở dữ liệu: migration Laravel và SQL Supabase tương ứng, RLS cho bản kiểm kê; `schema.sql` phản ánh thay đổi sau khi áp dụng.
- Kiểu mã theo PSR-12/Pint; tên cột mới theo quy ước PascalCase tiếng Việt của bảng hiện có.
- Lệnh kiểm thử: `php artisan test --filter=BookingOrderConversionTest`; kiểm thử PostgreSQL theo `.github/workflows/verification.yml`; `npm run build` cho giao diện.
- Kiểm thử bắt buộc: xác nhận không tạo đơn, hai phiếu đúng một lần, hủy không sinh phiếu, kiểm kê không chênh lệch tạo một đơn, chênh lệch chặn đơn đến khi khách duyệt, phiên bản cũ bị từ chối, không thể xác nhận Booking của khách khác.

## Giới hạn triển khai

- Giữ nguyên giá trị các đơn cũ và luồng kiểm kê legacy `Chờ tiếp nhận`.
- Không mở quyền `UPDATE` rộng trên bản kiểm kê cho client Supabase; chỉ cho cập nhật cột xác nhận phiên bản cùng RLS.
- Web và migration được triển khai trước; giao diện Flutter cần repo mobile để tích hợp sau.
