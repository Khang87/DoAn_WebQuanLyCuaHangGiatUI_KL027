# Booking → đơn hàng sau kiểm kê

## Mục tiêu và phạm vi

Booking từ mobile là yêu cầu đặt lịch, chưa phải đơn hàng. Nhân viên xác nhận hoặc hủy Booking mà không sửa chi tiết trong thao tác xác nhận. Khi xác nhận, hệ thống lập hai chứng từ gắn Booking: chặng nhận và chặng trả, kể cả khi chặng diễn ra tại cửa hàng. `DonHang` chỉ được tạo sau kiểm kê. Form kiểm kê lấy sẵn thông tin Booking. Nhân viên gặp khách, đối chiếu đồ thực tế, trao đổi trực tiếp nếu có sai khác, sửa thông tin và xác nhận để tạo đơn. Mobile nhận thông báo sau khi có điều chỉnh, không cần duyệt để tạo đơn.

## Bản đồ năng lực

| Mã | Trách nhiệm | Phụ thuộc |
|---|---|---|
| booking-approval | Chuyển `ChoTiepNhan` → `DaXacNhan` hoặc `DaHuy`, ghi người/thời điểm xác nhận | Booking hiện tại |
| booking-legs | Lập hai phiếu dự kiến gắn Booking, giữ loại/hình thức/địa chỉ/phí của từng chặng | booking-approval |
| booking-inspection | Lưu bản kiểm kê thực tế từ thông tin Booking đã đối chiếu và chỉnh sửa | booking-approval |
| order-conversion | Chỉ tạo một đơn từ Booking đã xác nhận và bản kiểm kê hợp lệ; kế thừa voucher, điểm, phí | booking-legs, booking-inspection |

Thứ tự: booking-approval → booking-legs → booking-inspection → order-conversion.

## Hợp đồng trạng thái

| Trạng thái | Đơn hàng | Thao tác tiếp theo |
|---|---|---|
| Booking `ChoTiepNhan` | Không có | Xác nhận hoặc hủy |
| Booking `DaXacNhan`, chưa kiểm kê | Không có | Nhận đồ, kiểm kê |
| Kiểm kê không chênh lệch | Chưa có đến khi nhân viên chốt | Tạo đơn từ bản kiểm kê |
| Thông tin thực tế khác Booking | Chưa có đến khi nhân viên chốt | Trao đổi trực tiếp với khách, sửa và xác nhận để tạo đơn; gửi thông báo mobile |
| Booking `DaHuy` | Không có | Không tạo đơn |

Chứng từ chặng Booking được lưu riêng vì `GiaoNhan.DonHangID` hiện bắt buộc. Khi có đơn, các chặng tại nhà được sao chép sang `GiaoNhan` theo hợp đồng đơn hiện tại; chứng từ Booking vẫn là lịch sử trước đơn.

## Liên kết phiếu Nhận → phiếu Giao

- Xác nhận Booking tạo đồng thời hai dòng `BookingGiaoNhan` (`NHAN_DO`, `GIAO_DO`) với cùng `BookingID`. Phiếu Giao giữ hình thức, địa chỉ và phí dự kiến; chưa có danh sách đồ chính thức.
- Phiếu Nhận phải ở trạng thái `Đã nhận` và cả hai phiếu phải còn tồn tại trước khi chuyển đổi. Bản `BookingKiemKe` chứa các món thực tế đã được nhân viên xác nhận.
- Trong cùng giao dịch tạo `DonHang` và `ChiTietDonHang`, hệ thống gắn `DonHangID` và `BookingKiemKeID` cho **cả hai** phiếu Booking. Phiếu Giao đọc mặt hàng/tình trạng đã chốt qua đơn và dùng liên kết kiểm kê để truy vết nguồn. Nếu thiếu một phiếu hoặc liên kết thất bại, toàn bộ giao dịch bị hủy.
- Trạng thái phiếu Giao vẫn là `Chờ thực hiện` sau khi tạo đơn; việc đối soát dữ liệu không có nghĩa đồ đã giặt xong hoặc đã giao. Các bản ghi `GiaoNhan` của đơn chỉ tạo cho chặng tại nhà như quy tắc hiện tại.

## Giao diện và bảo mật

- Trang chi tiết Booking có hai thao tác riêng: xác nhận hoặc hủy; form kiểm kê chỉ mở sau xác nhận.
- Form kiểm kê điền sẵn dữ liệu Booking để nhân viên đối chiếu, chỉ chỉnh những phần sai thực tế sau khi trao đổi với khách. Mã Booking, khách hàng, ưu đãi và phí đã lưu chỉ đọc.
- Nếu nhân viên điều chỉnh thông tin Booking, hệ thống gửi thông báo đến đúng tài khoản khách sau khi tạo đơn. Mobile chỉ được đọc bản kiểm kê thuộc `KhachHangID` của phiên Supabase hiện tại; không được sửa hoặc duyệt bản kiểm kê.
- Laravel kiểm tra lại trạng thái, bản kiểm kê đã lưu và dữ liệu tài chính trong giao dịch khóa hàng trước khi tạo đơn. Tạo đơn lặp phải trả cùng một đơn.

## Cấu trúc, mã và kiểm thử

- Laravel: `app/Services/BookingService.php`, `app/Services/OrderService.php`, `app/Http/Controllers/Admin/BookingController.php`, Blade trong `resources/views/admin/bookings/`.
- Cơ sở dữ liệu: migration Laravel và SQL Supabase tương ứng, RLS cho bản kiểm kê; `schema.sql` phản ánh thay đổi sau khi áp dụng.
- Kiểu mã theo PSR-12/Pint; tên cột mới theo quy ước PascalCase tiếng Việt của bảng hiện có.
- Lệnh kiểm thử: `php artisan test --filter=BookingOrderConversionTest`; kiểm thử PostgreSQL theo `.github/workflows/verification.yml`; `npm run build` cho giao diện.
- Kiểm thử bắt buộc: xác nhận không tạo đơn, hai phiếu đúng một lần, hủy không sinh phiếu, kiểm kê không chênh lệch tạo một đơn, chênh lệch được lưu và thông báo sau khi tạo đơn, mobile không thể sửa bản kiểm kê hoặc đọc Booking của khách khác.

## Giới hạn triển khai

- Giữ nguyên giá trị các đơn cũ và luồng kiểm kê legacy `Chờ tiếp nhận`.
- Không cấp quyền `UPDATE` bản kiểm kê cho client Supabase; Laravel là bên duy nhất ghi dữ liệu.
- Web và migration được triển khai trước; Flutter cần hiển thị thông báo điều chỉnh khi tích hợp sau.
