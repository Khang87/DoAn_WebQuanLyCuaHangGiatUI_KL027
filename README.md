# Sky Laundry

Sky Laundry là hệ thống quản lý cửa hàng giặt ủi, hỗ trợ quy trình vận hành cho Chủ cửa hàng, Quản lý, Nhân viên và Khách hàng. Ứng dụng gồm giao diện web Laravel và API JSON phiên bản hóa, sử dụng cơ sở dữ liệu PostgreSQL trên Supabase theo cấu trúc hiện có.

## Cập nhật Booking và reset mật khẩu — 07/10/2026

- Booking sử dụng hai chiều độc lập: `HinhThucNhanDo`/`DiaChiNhan` và `HinhThucTraDo`/`DiaChiTra`. `ReceiveMethod` và `ReturnMethod` đều có hai địa điểm: `Tại cửa hàng`, `Tại nhà`. Không suy ra `NHAN_DO`/`GIAO_DO` từ địa điểm.
- Danh sách và chi tiết hiển thị cả nhận/trả; form sửa có hai dropdown riêng và yêu cầu địa chỉ cho từng chiều tại nhà. Tìm kiếm bao gồm địa chỉ trả; bộ lọc nhận/trả hoạt động độc lập.
- Luồng hiện hành: **Booking chờ xác nhận → mở form kiểm tra thực tế → Xác nhận & tạo đơn → Đã tiếp nhận**. GET `/bookings/{booking}/inspection` chỉ mở form. POST `/bookings/{booking}/confirm` yêu cầu nhân viên, thông tin nhận/trả và các dòng thực tế; các dòng bắt buộc có `TinhTrangTruocKhiGiat` tối đa 320 ký tự. Cả hai endpoint sử dụng quyền `bookings.confirm`.
- Luồng service duy nhất là `BookingService::inspectBookingAndCreateOrder()`. Các dòng dự kiến `ChiTietBooking` được giữ làm lịch sử; `ChiTietDonHang` được tạo từ dữ liệu thực tế. Server tra giá hiệu lực theo đúng dịch vụ/loại đồ/đơn vị, không tin đơn giá hoặc thành tiền gửi từ trình duyệt. KG tối thiểu vẫn áp dụng riêng cho từng dòng.
- Chỉ tạo phiếu `NHAN_DO` khi nhận tại nhà và `GIAO_DO` khi trả tại nhà. Nếu cả hai tại cửa hàng thì không tạo phiếu. Phiếu nhận dùng `NgayHen`/`GioHen`; phiếu trả chưa có lịch thì để `ThoiGianDuKien` NULL, không sao chép thời gian nhận làm thời gian trả.
- Việc tạo đơn, chi tiết, phiếu, trừ điểm và xác nhận/audit Booking nằm trong cùng transaction. Khóa Booking và trả về đơn hiện hữu khi gửi lại giúp tránh tạo đơn hoặc trừ điểm trùng. Audit ghi cả `HinhThucTraDo` và `DiaChiTra`, bao gồm các lần sửa thông thường.
- Booking cũ thiếu hình thức trả hiển thị “Chưa bổ sung”; nhân viên phải chọn trước khi chuyển đổi. Đơn cũ đang `Chờ tiếp nhận` tiếp tục dùng bước kiểm tra trên trang đơn hàng; bản sửa không tự chuyển trạng thái các đơn cũ.
- Admin reset mật khẩu bằng cách gửi email OTP qua `PasswordResetOtpService`, dùng chung với luồng quên mật khẩu. Không đặt mật khẩu cố định hoặc hiển thị mật khẩu/OTP trong flash message. OTP ngẫu nhiên 6 chữ số, lưu dạng hash, hiệu lực 15 phút; giới hạn yêu cầu lại 1 phút. Gửi mail lỗi xóa OTP/throttle; mật khẩu chỉ đổi khi người dùng hoàn tất form khôi phục. Cần cấu hình Resend hoạt động và email hợp lệ; không gửi email thật trong kiểm thử.
- `TaiKhoan::$fillable` được bổ sung `AvatarURL`. Luồng upload avatar hiện hành được giữ nguyên.
- `schema.sql` vẫn là snapshot tham chiếu. Các cột legacy `HinhThucGiaoDo`/`DiaChiGiao` được chú thích và không dùng trong Laravel; không xóa khỏi snapshot khi chưa đối chiếu lại catalog Live. Phiên làm việc này không có credential để kiểm tra catalog Supabase và không thực thi SQL/migration/seeder trên Live.

## Công nghệ sử dụng

- PHP 8.3 trở lên
- Laravel 13.x (`laravel/framework` `^13.17`)
- PostgreSQL trên Supabase
- Blade, giao diện dựa trên Bootstrap, Tailwind CSS 4 và Vite
- PHPUnit 12

Thông tin phiên bản được đối chiếu từ các tệp cấu hình phụ thuộc của dự án; dự án hiện không dùng Laravel 10 hay PHP 8.1.

## Triển khai production

- Domain production: [https://do-an-web-quan-ly-cua-hang-giat-ui.vercel.app](https://do-an-web-quan-ly-cua-hang-giat-ui.vercel.app).
- Kiểm tra công khai ngày 06/10/2026: `/login` trả HTTP 200; `/profile` yêu cầu đăng nhập và chuyển về trang đăng nhập.
- Ảnh đại diện dùng biến môi trường `SUPABASE_AVATAR_BUCKET`; Bucket ID chính xác là `avatars` (phân biệt chữ hoa/thường). Cấu hình Laravel giữ nguyên casing thay vì tự chuyển sang chữ hoa.
- Trên production, bucket `avatars` đã được xác nhận; sau deploy `2776f72`, kiểm thử ảnh PNG 1×1 hoàn tất: xin signed URL, upload Storage, xác minh object và lưu URL hồ sơ đều thành công (HTTP 200), ảnh vẫn hiển thị sau khi tải lại trang. Ảnh đại diện cũ của tài khoản test được thay bằng ảnh thử theo xác nhận của người dùng; không thay đổi schema, migration hay cấu hình bucket. Xem [TESTCASES.md](./TESTCASES.md).

## Giới thiệu dự án

Sky Laundry hỗ trợ số hóa hoạt động hằng ngày của cửa hàng giặt ủi: tiếp nhận lịch hẹn và đơn hàng, quản lý dịch vụ và bảng giá, theo dõi xử lý/giao nhận, lập hóa đơn, ghi nhận thanh toán và xem báo cáo. Hệ thống có các quyền riêng cho Chủ cửa hàng, Quản lý, Nhân viên và Khách hàng; giao diện web phục vụ vận hành cửa hàng, còn API cung cấp dữ liệu cho các ứng dụng client được cấp quyền.

## Tính năng chính

- Kiểm tra vai trò và quyền tại middleware của route, Gate/controller và giao diện Blade.
- Một tài khoản có thể mang nhiều vai trò qua bảng `TaiKhoan_VaiTro`; quyền vai trò được liên kết qua `VaiTro_Quyen` và `Quyen`.
- Tài khoản không hoạt động (`TaiKhoan.TrangThai`) không được hưởng quyền bỏ qua kiểm tra của Chủ cửa hàng hoặc quyền truy cập theo mã quyền.
- Hỗ trợ nghiệp vụ đơn hàng, đặt lịch, hóa đơn, thanh toán, giao nhận, khách hàng, dịch vụ, bảng giá, khuyến mãi và báo cáo.
- Booking hỗ trợ nhiều dòng dịch vụ; mức khối lượng tối thiểu được tính riêng trên từng dòng KG, không cộng gộp toàn Booking.
- Bộ máy bảng giá tra cứu chính xác theo `(DichVuID, LoaiDoGiatID, DonViTinhID)`, lấy ngày áp dụng mới nhất; thao tác tạo/sửa/khôi phục khóa theo tuple bằng PostgreSQL transaction advisory lock để chống race condition giữa các tiến trình Laravel.
- Booking được xác nhận sẽ tự chuyển thành đơn hàng và phiếu giao trong transaction. UI không hiện nút tạo đơn cho Booking đang chờ xác nhận; nếu đã có đơn thì hiển thị liên kết tới đơn hiện hữu thay vì tạo trùng.
- Booking chỉ được tiếp nhận và chuyển thành đơn khi đã chọn nhân viên phụ trách hợp lệ. Sau khi đơn được tạo từ Booking, nhân viên phụ trách bị khóa trên form sửa; backend cũng từ chối request cố thay đổi người phụ trách.
- Form sửa Booking lọc dịch vụ theo danh mục đã chọn. Danh mục không có dịch vụ sẽ hiển thị thông báo và vô hiệu hóa ô dịch vụ; đơn vị tính tiếp tục chỉ khả dụng khi có bảng giá hiệu lực phù hợp.
- Điểm tích lũy dùng các hằng số nghiệp vụ trong `OrderService`: cứ đủ 1.000 VNĐ `ThanhTien` của đơn sẽ được cộng 100 điểm khi đơn chuyển sang `Đã giao` (tính theo phần nguyên; ví dụ `ThanhTien` 12.500 VNĐ được 1.200 điểm). Khi đổi điểm, 1 điểm giảm 1 VNĐ, nên 1.000 điểm giảm 1.000 VNĐ. Form tạo/sửa đơn và xác nhận Booking dùng công tắc bật/tắt thay cho nhập số điểm; khi bật, hệ thống tự giới hạn số điểm theo số dư và số tiền còn phải trả. Cộng điểm có audit và chống cộng lặp; giao dịch tạo/sửa đơn hoàn điểm cũ, trừ điểm mới nguyên tử và rollback nếu số dư không đủ.
- Form khuyến mãi tự bật/tắt trường “Mức giảm tối đa” theo loại khuyến mãi: giảm cố định sẽ vô hiệu hóa và xóa mức tối đa; giảm theo phần trăm cho phép nhập mức tối đa.
- Hồ sơ tài khoản hỗ trợ tải avatar trực tiếp lên Supabase Storage bằng signed upload URL; ứng dụng chỉ lưu URL công khai trong hồ sơ tài khoản và kiểm tra đường dẫn avatar thuộc đúng tài khoản đang đăng nhập.
- Danh sách khách hàng hỗ trợ sắp xếp theo tổng điểm tích lũy, xử lý khách chưa có bản ghi điểm như 0 điểm và sắp xếp ổn định khi bằng điểm.
- Ghi nhật ký `NhatKyHeThong` cho tạo/xác nhận Booking (bao gồm người và thời điểm xác nhận), đổi trạng thái đơn hàng và thay đổi tài khoản; snapshot tài khoản không ghi mật khẩu.
- Màn hình Nhật ký hệ thống tại `/admin/system-logs` dành riêng cho Chủ cửa hàng; có lọc theo bảng dữ liệu, hành động, khoảng ngày và dropdown tài khoản, phân trang, xem snapshot trước/sau và liên kết tới Booking/đơn liên quan. Nhật ký có `TaiKhoanID` NULL được hiển thị là “Hệ thống”; thời gian được trình bày ngày/giờ thành hai dòng và cặp nút “Lọc”/“Xóa lọc” dùng cùng kích thước, căn chỉnh.
- Chat theo đơn hàng tại `/admin/messages` cho Chủ cửa hàng, Quản lý và Nhân viên; danh sách đơn phân trang, xem tối đa 100 tin nhắn gần nhất theo đơn và gửi tới tài khoản khách hàng được liên kết với đơn. Nhãn người gửi phân biệt tin của “Cửa hàng” với tên tài khoản người gửi; `TinNhan` chỉ lưu hội thoại, không dùng làm technical audit hoặc system log.
- Chức năng Cài đặt hệ thống đã được loại bỏ: không còn trang/menu hay route `/settings`; quản lý hồ sơ cá nhân và đổi mật khẩu vẫn hoạt động riêng.
- Tên bảng/cột tuân thủ chính xác cách viết của Supabase PostgreSQL: phần lớn bảng nghiệp vụ PascalCase, riêng một số đối tượng live như `khachhang_diachi` và các cột của nó viết thường.
- Có cơ chế bảo vệ các lệnh Artisan có thể phá hủy cấu trúc cơ sở dữ liệu.

## Cấu trúc Supabase Live

`schema.sql` là snapshot cấu trúc chỉ đọc của PostgreSQL Supabase, dùng làm nguồn tham chiếu khi ánh xạ Eloquent. Không chạy migration/DDL hoặc seeder trên Supabase Live.

Phần lớn bảng nghiệp vụ dùng tên PascalCase, ví dụ `Booking`, `BangGia`, `DonHang`; không tự động đổi casing vì PostgreSQL giữ nguyên tên identifier đã được quote. Bảng sổ địa chỉ là ngoại lệ lowercase theo schema live:

| Đối tượng | Tên trong schema |
|---|---|
| Bảng địa chỉ | `khachhang_diachi` |
| Khóa chính | `diachiid` (`GENERATED ALWAYS AS IDENTITY`) |
| Khóa ngoại khách hàng | `khachhangid` → `"KhachHang"("KhachHangID")` |
| Các cột còn lại | `tennguoinhan`, `sodienthoai`, `diachi`, `ghichu`, `macdinh`, `ngaytao` |

Model `KhachHangDiaChi` khai báo đúng tên bảng/khóa/cột lowercase; `diachiid` không nằm trong `$fillable` để PostgreSQL tự sinh giá trị identity. Quan hệ khách hàng dùng khóa ngoại `khachhangid` và khóa cha `KhachHangID`.

Kiểm tra chống khoảng giá chồng lấn hiện được thực thi trong ứng dụng khi thao tác qua Laravel. Advisory lock có hiệu lực trong transaction của PostgreSQL; vì schema live chưa khai báo exclusion constraint, thao tác ghi trực tiếp ngoài Laravel không được bảo vệ bởi kiểm tra này.

## Sơ đồ phân quyền hệ thống

Quyền truy cập được kiểm tra theo vai trò và mã quyền. Việc ẩn nút trên giao diện chỉ hỗ trợ trải nghiệm người dùng; quyết định cho phép hay từ chối vẫn được thực hiện ở tầng route và controller/Gate.

| Chức năng | Chủ cửa hàng | Quản lý | Nhân viên | Khách hàng |
|---|---|---|---|---|
| Quản lý vai trò và ma trận quyền | Toàn quyền | Không được cấp quyền quản lý ma trận | Không được cấp quyền quản lý ma trận | Không được cấp quyền quản lý ma trận |
| Dashboard và báo cáo quản lý | Theo quyền Chủ cửa hàng | Theo quyền được cấp | Chỉ truy cập chức năng được cấp | Bị chặn khỏi khu vực quản trị |
| Đơn hàng, khách hàng, dịch vụ và giao nhận | Theo quyền Chủ cửa hàng | Theo ma trận quyền | Theo ma trận quyền từng thao tác | Bị chặn khỏi giao diện/API quản trị hiện tại |
| Hóa đơn và thanh toán | Theo quyền Chủ cửa hàng | Theo quyền được cấp và quy tắc khóa bản ghi đã quyết toán | Chỉ khi được cấp quyền phù hợp | Bị chặn khỏi giao diện/API quản trị hiện tại |
| Nhật ký hệ thống (`NhatKyHeThong`) | Được truy cập | Bị từ chối | Bị từ chối | Bị chặn khỏi khu vực quản trị |
| Chat theo đơn hàng (`TinNhan`) | Được truy cập | Được truy cập | Được truy cập | Bị chặn khỏi giao diện quản trị |
| Tài khoản không hoạt động | Không được bypass quyền | Bị từ chối | Bị từ chối | Bị từ chối |

Tài khoản có thể có nhiều vai trò thông qua `TaiKhoan_VaiTro`. Quyền được gán cho vai trò qua `VaiTro_Quyen` và bảng `Quyen`. Những thao tác đặc biệt với bản ghi đã thanh toán tiếp tục chịu quy tắc nghiệp vụ riêng; không suy diễn rằng chỉ dựa vào vai trò là đủ quyền.

## Các module chính

| Module | Nội dung |
|---|---|
| Tài khoản và phân quyền | Tài khoản, vai trò, quyền và quản lý phiên truy cập |
| Khách hàng và đặt lịch | Hồ sơ khách hàng, tiếp nhận và xác nhận lịch hẹn |
| Đơn hàng và chi tiết đơn | Theo dõi trạng thái, dịch vụ, khối lượng/số lượng và thành tiền |
| Dịch vụ, loại đồ giặt và bảng giá | Quản lý danh mục dịch vụ, loại đồ, đơn vị tính và giá áp dụng |
| Hóa đơn và thanh toán | Lập hóa đơn, ghi nhận thanh toán và bảo vệ bản ghi đã quyết toán |
| Giao nhận | Theo dõi lịch và trạng thái giao/nhận đồ |
| Khuyến mãi và mã giảm giá | Quản lý chương trình và mã áp dụng |
| Đánh giá và thông báo | Theo dõi phản hồi, trả lời đánh giá và thông báo nghiệp vụ |
| Nhật ký hệ thống | Tra cứu audit `NhatKyHeThong` theo bảng, hành động, thời gian và tài khoản (Chủ cửa hàng) |
| Tin nhắn | Hội thoại hai chiều giữa cửa hàng và khách hàng theo đơn hàng, lưu trong `TinNhan` |
| Báo cáo | Tổng hợp chỉ số vận hành, doanh thu và dữ liệu dịch vụ |
| API | Cung cấp một số danh sách và thao tác đã được xác thực, phân quyền |

### Tỷ lệ điểm tích lũy

Các tỷ lệ nằm tại `App\Services\OrderService`:

| Hằng số | Giá trị | Ý nghĩa |
|---|---:|---|
| `POINTS_PER_AMOUNT` | 1.000 VNĐ `ThanhTien` | Mốc giá trị thanh toán để tính thưởng |
| `POINTS_EARNED_PER_AMOUNT` | 100 điểm | Điểm thưởng cho mỗi mốc `POINTS_PER_AMOUNT` khi đơn chuyển sang `Đã giao` |
| `POINT_VALUE` | 1 VNĐ/điểm | Giá trị giảm giá; ví dụ 1.000 điểm giảm 1.000 VNĐ |

Điểm thưởng được tính theo phần nguyên của `ThanhTien / POINTS_PER_AMOUNT`, nhân `POINTS_EARNED_PER_AMOUNT`. Điểm sử dụng được giới hạn bởi số dư và phần tiền còn lại sau khuyến mãi.

## Tài khoản demo hệ thống

Thông tin tài khoản mẫu phải được lấy từ seeder hoặc môi trường demo do quản trị viên cung cấp. README không công bố email/mật khẩu mặc định vì thông tin này có thể không còn khớp với mã nguồn hiện tại và không nên dùng làm thông tin xác thực dùng chung.

Chỉ sử dụng tài khoản được cấp trong môi trường cục bộ hoặc môi trường demo đã được phê duyệt. Không chạy seeder để tạo tài khoản trên Supabase Live và không đưa thông tin đăng nhập vào Git.

## Tiến độ phát triển

### Đã hoàn thành

- Triển khai RBAC động dựa trên `TaiKhoan_VaiTro`, `VaiTro_Quyen` và `Quyen`, tương thích với schema PascalCase hiện có.
- Bổ sung tra cứu quyền trong phạm vi request và làm mới cache sau khi thay đổi quyền của vai trò.
- Kiểm tra trạng thái tài khoản trong cơ chế quyền Chủ cửa hàng, middleware vai trò và middleware quyền.
- Tách quyền resource theo từng hành động và kiểm tra quyền cụ thể trên các API được bảo vệ.
- Bổ sung kiểm thử hồi quy về việc Khách hàng bị chặn khỏi trang quản trị/API và giới hạn quyền theo từng hành động.
- Bổ sung logic giao diện tạo đơn để chuyển đổi giữa nhập khối lượng và số lượng; phép tính phía máy chủ được xử lý bởi `TinhTienGiatUiService`.
- Hoàn thiện trang Báo cáo theo bộ lọc thời gian: KPI đơn hàng/đặt lịch, doanh thu theo ngày lập hóa đơn đã thanh toán, giá trị đơn trung bình, xu hướng doanh thu và cơ cấu doanh thu dịch vụ; hai biểu đồ dùng ApexCharts được phục vụ từ tài nguyên cục bộ.
- Thống kê phương thức thanh toán từ giao dịch thành công trên `ThanhToan`, liên kết với hóa đơn đã thanh toán; danh sách đơn gần đây nạp sẵn thông tin khách hàng và chi tiết dịch vụ.
- Xuất Excel danh sách hóa đơn vẫn được hỗ trợ tại module Hóa đơn; chức năng xuất Excel ở Báo cáo và Chi tiết hóa đơn đã được gỡ bỏ.
- Giữ thông báo rõ ràng cho chức năng điều kiện đồ giặt chưa được schema hiện tại hỗ trợ, không ghi dữ liệu vào bảng không tồn tại.

### Các vấn đề đã khắc phục

- Sửa các trường hợp tạo URL thiếu tham số và các ánh xạ PascalCase trong những phần đã rà soát.
- Sửa quan hệ trang chi tiết loại đồ giặt để dùng `LoaiDoGiat` và dữ liệu bảng giá đúng theo schema.
- Xóa một số lớp tương thích không còn tham chiếu, mã báo cáo trùng lặp, view không được sử dụng và test mẫu mặc định.
- Khắc phục lỗ hổng phân quyền do dùng chung danh sách quyền OR cho nhiều thao tác và do API thiếu kiểm tra quyền.
- Loại bỏ route, menu, trang và ánh xạ quyền riêng của Cài đặt hệ thống; giữ nguyên các chức năng hồ sơ cá nhân trong module tài khoản.
- Đồng bộ các truy vấn báo cáo với `HoaDon.ThanhTien`, `HoaDon.NgayLap`, trạng thái thanh toán và trạng thái đặt lịch theo schema hiện tại.
- Tối ưu KPI báo cáo bằng một truy vấn tổng hợp trạng thái đơn; tra cứu bucket biểu đồ qua collection đã lập chỉ mục thay vì quét lại toàn bộ kết quả cho từng ngày/tuần.
- Các biểu đồ doanh thu dashboard tổng hợp theo giờ/ngày/tháng bằng một truy vấn, thay vì phát sinh một truy vấn cho từng bucket.
- Danh mục ít thay đổi dùng cache store cấu hình; trên Vercel mặc định dùng Redis nếu `CACHE_STORE` chưa được đặt. Khi triển khai Upstash, cần cấu hình kết nối Redis tương ứng trong biến môi trường và không đưa thông tin bí mật vào Git.
- Tối ưu dashboard nhân viên bằng cách đếm lịch nhận/giao tại database; danh sách giao nhận eager-load quan hệ và chỉ lấy các cột cần hiển thị.
- Cải thiện xử lý điểm tích lũy khi sửa đơn để chỉ điều chỉnh phần chênh lệch; đồng bộ trạng thái thanh toán theo lifecycle đơn hàng.
- Cải thiện tạo và quản lý thông báo: tiêu đề bắt buộc, thời gian gửi lấy từ server, đánh dấu đã đọc giới hạn theo tài khoản hiện tại và không gửi thông báo khuyến mãi hàng loạt ngoài ý muốn.
- Gỡ chức năng xuất Excel ở Báo cáo và Chi tiết hóa đơn; giữ nguyên xuất Excel danh sách hóa đơn tại module Hóa đơn.

### Trạng thái kiểm thử hồi quy

Bản vá kiểm kê đơn tạo trực tiếp ngày **07/10/2026**: **310 PASSED, 0 skipped, 0 failed, 1.648 assertions** (7,59 giây), PHP 8.4 trong container tắt mạng, SQLite `:memory:`. Request/service chặn trạng thái khởi tạo sai và gắn Booking trực tiếp; form bắt buộc tình trạng đồ. Hai test pricing mới bắt được mutation đảo điều kiện ngày hết hạn. Các test Pending lịch sử tiếp tục chạy. Xem [hợp đồng tạo đơn trực tiếp](./docs/supabase/BUSINESS_RULES.md#đơn-tạo-trực-tiếp-trên-web).

Kết quả trước bản vá tạo đơn trực tiếp ngày **07/10/2026**: **303 PASSED, 0 skipped, 0 failed, 1.555 assertions** (5,85 giây), SQLite `:memory:` trong container PHP 8.4 tắt mạng; email dùng mock. Trong 192 case legacy trước đây, **8 case được khôi phục/chuyển sang fixture hiện hành và 184 case được loại khỏi suite sau phân loại**; không tính case bị loại là passed hoặc khẳng định đã thay thế 1:1. Xem [bảng kiểm kê legacy](./docs/testing/LEGACY_TESTS.md) và [TESTCASES.md](./TESTCASES.md). Laravel Pint, Blade cache và route cache đều thành công.

Các kiểm tra nghiệp vụ RPC chạy thành công trên PostgreSQL 17 cục bộ, dùng fixture riêng và rollback dữ liệu thử nghiệm. **13 hàm hiện có đã được cập nhật trên Supabase Live**; kiểm tra catalog trước/sau xác nhận không đổi bảng/cột, trigger, ràng buộc, chữ ký hàm, owner hoặc ACL. Không tạo migration và không ghi fixture lên Live. Xem [hợp đồng nghiệp vụ Web/App](./docs/supabase/BUSINESS_RULES.md) và [đối chiếu schema](./docs/supabase/SCHEMA_AUDIT.md). Không có mã nguồn Flutter trong repo này, nên chưa kiểm thử giao diện Flutter end-to-end; RPC xác nhận booking mới yêu cầu kiểm kê qua Web.

### Đồng bộ nghiệp vụ ngày 07/10/2026

- Giá hiệu lực tập trung tại `PricingService`, dùng chung cho booking, đơn và chi tiết đơn; chốt giá khi kiểm kê.
- Điểm: **1 điểm = 1đ**, trừ khi tạo đơn sau kiểm kê; hoàn khoản giữ chỗ cũ và chống cộng/hoàn lặp.
- Khuyến mãi được áp dụng trước điểm; phí giao nhận cộng sau giảm giá. Thanh toán khóa đơn trước khoản thu và không vượt số tiền còn phải thu.
- Trạng thái đơn tuân thủ thứ tự xử lý; hủy trước khi giặt phải có lý do, không có khoản thu thành công.
- Giao nhận phân biệt **Loại giao nhận** (Nhận đồ/Giao đồ) và **Hình thức** (Tại cửa hàng/Tại nhà). Bốn cột phí/khoảng cách Booking đã có trên Live được bổ sung vào snapshot; các cột tương thích vẫn đang được RPC sử dụng được giữ lại.

### Cập nhật tính năng (06/10/2026)

- **Tiếp nhận Booking:** Bắt buộc chọn nhân viên phụ trách trước khi tiếp nhận và tạo đơn. Sau khi Booking có đơn hàng liên kết, nhân viên phụ trách không thể sửa; cả giao diện và backend đều bảo vệ trạng thái này.
- **Dịch vụ theo danh mục:** Form sửa Booking có thêm chọn danh mục; danh sách dịch vụ thay đổi theo danh mục. Nếu danh mục không có dịch vụ, form nêu rõ và khóa lựa chọn dịch vụ. Các lựa chọn đơn vị/khối lượng cũ được làm mới khi đổi danh mục.
- **Công tắc dùng điểm:** Tạo/sửa đơn và xác nhận Booking dùng công tắc thay vì nhập số điểm. Khi bật, giao diện dự tính số điểm tối đa áp dụng được sau khuyến mãi; server vẫn tự tính và xác thực số điểm thực tế trong transaction.
- **Khuyến mãi:** Trường mức giảm tối đa bị làm xám/vô hiệu hóa với mức giảm cố định và được gửi rỗng để loại bỏ giới hạn cũ; trường này được bật với mức giảm phần trăm.
- **Ảnh hồ sơ:** Tải avatar qua signed URL lên Supabase Storage; server xác minh object thuộc tài khoản hiện tại trước khi lưu URL.
- **Triển khai:** Domain production hiện tại là [do-an-web-quan-ly-cua-hang-giat-ui.vercel.app](https://do-an-web-quan-ly-cua-hang-giat-ui.vercel.app). Bản deploy `2776f72` đã chạy thành công luồng upload avatar production với bucket `avatars`; signed URL, upload Storage, xác minh public image và lưu URL hồ sơ đều thành công, ảnh còn hiển thị sau reload.
- **Snapshot Supabase:** `schema.sql` được cập nhật theo truy vấn metadata chỉ đọc ngày 06/10/2026. Snapshot phản ánh thêm các cột mới của `Booking`/`KhachHang`, kiểu `Booking.DiaChiNhan` và các ràng buộc Booking; không chạy DDL, migration hay ghi dữ liệu lên Supabase.
- **Kiểm thử:** Lần chạy tập trung mới nhất cho `BookingOrderConversionTest` đạt **35 passed, 180 assertions**. Đây là test SQLite in-memory, không phải chạy toàn bộ suite hoặc kiểm thử ghi trên Supabase Live.

### Cải tiến & tái cấu trúc Loại đồ giặt và bảng giá

#### Chuẩn hóa module `LoaiDoGiat`

- Hợp nhất chức năng quản lý danh mục loại đồ giặt về một module CRUD và một mục menu `LoaiDoGiat`; route chuẩn dùng tên `loaidogiat.*`. Các URL tương thích cũ chỉ chuyển hướng, không duy trì thêm module CRUD song song.
- Model và truy vấn tuân thủ tên bảng/cột PascalCase trong snapshot Supabase: `LoaiDoGiat` (`LoaiDoGiatID`, `TenLoaiDoGiat`, `MoTa`, `TrangThai`). Không đổi cấu trúc schema và không chạy migration trên Supabase Live.
- Xóa an toàn dữ liệu tham chiếu: trước khi xóa loại đồ giặt, hệ thống kiểm tra quan hệ với `BangGia`, `ChiTietDonHang` và `Booking`. Nếu đang được sử dụng, bản ghi được chuyển sang trạng thái **`Tạm ngưng`** thay vì xóa cứng; nếu không có dữ liệu liên quan, mới thực hiện xóa.

#### Bảng giá và trải nghiệm POS

- Khi chọn cặp dịch vụ – loại đồ giặt trên form tạo hoặc sửa đơn hàng, giao diện tra cứu bảng giá để tự điền đơn vị tính và đơn giá, đồng thời chuyển đổi phù hợp giữa nhập số lượng và khối lượng.
- Chỉ dùng bảng giá đang hoạt động, còn trong thời hạn áp dụng; khi có nhiều bản ghi phù hợp, ưu tiên `NgayApDung` mới nhất, sau đó dùng `BangGiaID` để phân định bản ghi cùng ngày.
- Dropdown loại đồ giặt được lọc theo các cặp dịch vụ – loại đồ có bảng giá hợp lệ và đơn vị tính. Khi đổi dịch vụ, lựa chọn cũ không còn hợp lệ được đặt lại; các dòng đã lưu trên form sửa được khởi tạo theo dữ liệu tương ứng.
- Đơn vị tính được lấy qua quan hệ `BangGia.DonViTinhID` tới bảng `DonViTinh`, còn đơn giá lấy từ `BangGia.DonGia`; không giả định đơn vị tính là một cột trực tiếp của `BangGia`.

#### Kiểm thử và an toàn dữ liệu

- Các kiểm thử schema/feature dùng SQLite trong bộ nhớ; kết quả hiện hành của toàn bộ suite được ghi ở mục **Trạng thái kiểm thử hồi quy** phía trên.
- Các bước xác minh của đợt refactor bao gồm biên dịch Blade, định dạng Laravel Pint và kiểm tra thay đổi Git.
- Kiểm thử dùng SQLite trong bộ nhớ. `schema.sql` chỉ là snapshot tham chiếu; không chạy DDL, migration hoặc thao tác ghi dữ liệu thử nghiệm lên Supabase Live.

### Rà soát đồng bộ Schema Supabase gần đây (01/10/2026)

#### Ánh xạ nghiệp vụ theo schema

- **Tài khoản (`TaiKhoan`):** Luồng tạo tài khoản tuân thủ ràng buộc chỉ liên kết với đúng một hồ sơ `NhanVien` hoặc `KhachHang`. Form hiện cho phép chọn hồ sơ đã tồn tại; tạo hồ sơ mới không nằm trong phạm vi chức năng này. Mật khẩu được ghi vào cột `MatKhau`, trạng thái tài khoản được quản lý qua `TrangThai`, và vai trò được liên kết qua `TaiKhoan_VaiTro`.
- **Giao nhận (`GiaoNhan`):** Dữ liệu giao nhận được ánh xạ vào các cột có trong schema như `DonHangID`, `NhanVienID`, `LoaiGiaoNhan`, `HinhThuc`, `DiaChi`, `ThoiGianDuKien`, `TrangThai` và `GhiChu`. Request không còn yêu cầu trường khách hàng riêng, vì khách hàng được xác định qua đơn hàng. Các luồng truy vấn cũng không giả định bảng có cột xóa mềm.
- **Khuyến mãi và đặt lịch:** Lượt sử dụng khuyến mãi được tính từ các đơn hàng tham chiếu thay vì xem `SoLuongSuDung` là hạn mức đổi mã. Quan hệ và kiểm tra dữ liệu liên quan của khuyến mãi/đặt lịch được đối chiếu với các khóa trong schema. Test hồi quy khuyến mãi nằm tại `tests/Unit/KhuyenMaiSchemaCompatibilityTest.php`.
- **Loại đồ giặt và bảng giá:** Tiếp tục dùng một module quản lý `LoaiDoGiat`; POS tra cứu `BangGia` để điền đơn vị tính và đơn giá, đồng thời lọc loại đồ theo cặp dịch vụ – bảng giá hợp lệ. Đơn vị tính được lấy từ quan hệ `DonViTinhID`, không giả định là cột trực tiếp trong `BangGia`.

#### Toàn vẹn dữ liệu và xác minh

- Với các module được rà soát, khi bản ghi đã có dữ liệu tham chiếu, ưu tiên giữ lịch sử bằng cách chuyển trạng thái sang giá trị ngừng hoạt động/tạm ngưng phù hợp với ràng buộc của bảng; chỉ xóa cứng khi không có liên kết cần bảo toàn.
- Đã gỡ các truy vấn `withTrashed()`/`onlyTrashed()` khỏi các service được rà soát khi bảng tương ứng không khai báo cột xóa mềm. Trạng thái nghiệp vụ được xử lý bằng các cột trạng thái thực tế thay vì cơ chế soft delete không có trong schema.
- Tuân thủ **Read-Only DDL**: không chạy migration, DDL hoặc thao tác ghi lên Supabase Live; `schema.sql` chỉ dùng làm snapshot tham chiếu.
- Test suite dùng SQLite in-memory và không đại diện cho kiểm thử tích hợp hoặc kiểm tra kết nối trực tiếp Supabase.

### Refactor Booking nhiều dòng, bảng giá và audit (02/10/2026)

#### Đã hoàn tất

- **Booking nhiều dòng (`ChiTietBooking`):** Booking lưu nhiều dòng dịch vụ dự kiến thay vì nhúng thông tin một dịch vụ vào bảng `Booking`. Mỗi dòng có dịch vụ, loại đồ, đơn vị tính, giá và thành tiền riêng. Validation ở Request và Service yêu cầu đúng một trong hai giá trị dương: `SoLuong` hoặc `KhoiLuong`, phù hợp với đơn vị tính.
- **Khối lượng tối thiểu:** Mức KG tối thiểu có thể cấu hình được tính cho từng dòng dịch vụ riêng; đơn giá nhân với `max(KhoiLuong, muc_toi_thieu)` của dòng đó.
- **Giá chuẩn hóa (`BangGia`):** Đơn giá được tra theo đúng bộ ba `(DichVuID, LoaiDoGiatID, DonViTinhID)`, chỉ lấy bản ghi đang hoạt động và còn hiệu lực, ưu tiên ngày áp dụng mới nhất, sau đó dùng `BangGiaID` để phân định cùng ngày. Khoảng hiệu lực chồng lấn bị từ chối khi tạo, cập nhật hoặc khôi phục giá. Service dùng advisory transaction lock theo tuple PostgreSQL nhằm tránh race-condition giữa các thao tác Laravel đồng thời. Máy chủ tính lại giá và thành tiền, không tin đơn giá do biểu mẫu gửi lên.
- **Chuyển Booking thành đơn:** Khi Booking được xác nhận, các dòng `ChiTietBooking` được ánh xạ thành các dòng `ChiTietDonHang` trong cùng giao dịch tạo đơn và phiếu giao. Thao tác xác nhận được ghi vào `NhatKyHeThong`, bao gồm dữ liệu Booking và chi tiết trước/sau cùng thông tin tài khoản thao tác khi có.
- **Audit:** `NhatKyHeThong` ghi nhận tạo/xác nhận Booking, chuyển trạng thái đơn hàng và thay đổi tài khoản. Sự kiện xác nhận lưu `NhanVienXacNhanID` và `ThoiGianXacNhan`; nội dung audit tài khoản không chứa mật khẩu.
- **Chống tạo đơn trùng:** Booking đã có đơn sẽ dẫn tới đơn hiện hữu; thao tác chuyển đổi là idempotent. Giao diện Booking hiển thị trạng thái chờ, đơn đã liên kết hoặc cảnh báo nếu Booking xác nhận nhưng chưa có đơn.
- **Ánh xạ địa chỉ:** `khachhang_diachi` và các cột `diachiid`, `khachhangid` giữ lowercase theo Supabase Live; model không mass-assign khóa identity.
- **Snapshot schema và an toàn:** `schema.sql` cục bộ đã được cập nhật làm tài liệu tham chiếu cho các cấu trúc `Booking`, `ChiTietBooking`, `BangGia` và `NhatKyHeThong` theo catalog Live đã kiểm tra. Không chạy migration hoặc DDL và không ghi dữ liệu thử nghiệm lên Supabase Live. Snapshot không thay thế bước xác minh trực tiếp từng đối tượng khi schema Live thay đổi.

#### Kiểm thử và chất lượng

- Lần chạy đầy đủ gần nhất và các ca được ghi chi tiết nằm tại mục **Trạng thái kiểm thử hồi quy** và [TESTCASES.md](./TESTCASES.md). Các nhóm bao gồm Booking nhiều dòng, audit, RBAC Nhật ký hệ thống, dropdown lọc tài khoản, chat theo đơn, điểm tích lũy, XOR số lượng/khối lượng, giá theo tuple, chặn overlap, ngày hiệu lực biên, tính phí KG theo từng dòng và quan hệ địa chỉ khách hàng.
- `php artisan view:cache`, `vendor/bin/pint --dirty --format agent`, kiểm tra lỗi trên các file PHP đã sửa và `git diff --check` đều hoàn tất thành công.
- Test chạy với SQLite in-memory; kết quả không phải kiểm thử tích hợp ghi dữ liệu trên Supabase Live.
- Thứ tự ưu tiên `NgayApDung` mới nhất và các mốc ngày biên đã có regression test trên SQLite. Chưa có test PostgreSQL tích hợp chạy đồng thời để chứng minh advisory lock/race-condition không deadlock; cũng chưa có test tích hợp riêng gọi từng endpoint Payment/Dashboard để xác nhận audit qua từng đường đi.

#### RPC `transition_laundry_order` — trạng thái và bước tiếp theo

- Chữ ký đã xác minh trên Supabase Live: `transition_laundry_order(p_donhangid bigint, p_trangthaimoi text, p_lydo text DEFAULT NULL) RETURNS void`. Function lấy định danh người thao tác từ `auth.uid()` trong Supabase JWT; chữ ký không có tham số `p_nhan_vien_id`.
- Kết nối PostgreSQL hiện dùng bởi Laravel chưa thiết lập JWT theo người dùng đăng nhập. Ngoài ra, RPC hiện không hỗ trợ trạng thái **“Đã thanh toán”**.
- Vì vậy, Web Admin hiện chưa được chuyển sang gọi RPC và việc tích hợp đang được hoãn. Bước tiếp theo là thiết kế cơ chế cấp/truyền JWT Supabase theo người dùng và thống nhất quy trình cho trạng thái thanh toán trước khi thay đổi các luồng chuyển trạng thái. Không truyền tham số ngoài chữ ký, giả mạo JWT hoặc bỏ qua kiểm tra phân quyền của function.

### Công việc dự kiến

- Thiết kế tích hợp RPC `transition_laundry_order` với JWT Supabase theo người dùng và thống nhất xử lý trạng thái thanh toán chưa được RPC hỗ trợ.
- Tiếp tục đối chiếu các ánh xạ của `DonHang`, `ChiTietDonHang` và `HoaDon` với snapshot mới nhất trong `schema.sql` và truy vấn thực tế.
- Chỉ xây dựng cổng Khách hàng xem đơn khi mọi truy vấn đều giới hạn theo `KhachHangID` của tài khoản đang đăng nhập.
- Giảm số test legacy đang bị bỏ qua và bổ sung kiểm thử trên schema được hỗ trợ, không kết nối test tới Supabase Live.

## Kiến trúc và nguyên tắc an toàn dữ liệu

- **Read-Only DDL:** Coi `schema.sql` là snapshot tham chiếu; có thể cập nhật snapshot cục bộ sau khi kiểm tra catalog chỉ đọc. Không chạy migration hoặc thực thi lệnh DDL trên Supabase Live.
- **Không ghi dữ liệu thử nghiệm lên môi trường thật:** Không cấu hình kiểm thử, seeder hoặc lệnh thiết lập cục bộ để ghi vào Supabase Live.
- `phpunit.xml` cấu hình bộ test dùng SQLite trong bộ nhớ (`:memory:`). Một số test tính năng hiện có sử dụng `RefreshDatabase`; thao tác này chỉ được phép trên kết nối SQLite trong bộ nhớ, tuyệt đối không đổi cấu hình để trỏ tới cơ sở dữ liệu Live.
- Không đưa thông tin đăng nhập trong `.env` lên Git hoặc chia sẻ chuỗi kết nối cơ sở dữ liệu.
- Không chạy seeder trên Supabase Live.

## Cấu trúc dự án

```text
.
├── app/
│   ├── Enums/
│   ├── Exceptions/
│   ├── Exports/
│   ├── Http/
│   │   ├── Controllers/       # Bộ điều khiển Admin, API, Auth và Staff
│   │   ├── Middleware/        # Kiểm tra vai trò và quyền
│   │   ├── Requests/          # Kiểm tra dữ liệu đầu vào
│   │   └── Resources/         # Định dạng phản hồi API
│   ├── Models/                # Eloquent Model theo schema
│   ├── Observers/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/              # Nghiệp vụ và tính toán
│   └── Support/               # Cache quyền, ánh xạ và hàm dùng chung
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/            # Tệp hiện có; không chạy trên Supabase
│   └── seeders/
├── resources/
│   └── views/                  # Giao diện Admin, Staff, xác thực và thành phần dùng chung
├── routes/
│   ├── api.php
│   └── web.php
├── schema.sql                  # Snapshot schema chỉ dùng làm tài liệu tham chiếu
├── tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
├── package.json
└── phpunit.xml
```

## Cài đặt và khởi chạy

Yêu cầu môi trường: PHP 8.3 trở lên, Composer, Node.js/npm và quyền truy cập một cơ sở dữ liệu PostgreSQL đã được khởi tạo sẵn theo `schema.sql`.

1. Cài các thư viện PHP và JavaScript:

   ```sh
   composer install
   npm install
   ```

2. Tạo tệp môi trường cục bộ và khóa ứng dụng:

   ```sh
   cp .env.example .env
   php artisan key:generate
   ```

   Trên Windows PowerShell, thay lệnh `cp` bằng `Copy-Item .env.example .env`.

3. Cấu hình `.env` bằng thông tin kết nối cơ sở dữ liệu được cấp cho môi trường phù hợp. Bảo mật thông tin đăng nhập. Không chạy migration, lệnh schema hoặc seeder trên Supabase.

4. Biên dịch tài nguyên giao diện và chạy máy chủ cục bộ:

   ```sh
   npm run build
   php artisan serve
   ```

   Khi phát triển giao diện có hot reload, chạy `npm run dev` trong một cửa sổ terminal khác.

## Lệnh kiểm tra

Chạy bộ kiểm thử bằng cấu hình SQLite trong bộ nhớ tại `phpunit.xml`:

```sh
php artisan test --compact
```

Hiển thị tên từng test để đối chiếu với [TESTCASES.md](./TESTCASES.md):

```sh
php artisan test --compact --testdox
```

Chạy Laravel Pint để định dạng mã PHP:

```sh
vendor/bin/pint
```

Liệt kê các route đã đăng ký; lệnh này không thay đổi cơ sở dữ liệu:

```sh
php artisan route:list
```

### Admin-triggered OTP và hộp thư mặc định

OTP nội bộ dùng Redis hiện có (10 phút), gửi cùng mã qua Resend và bảng `ThongBao`; không có thay đổi schema Supabase. Sau triển khai chạy `php artisan notifications:grant-default` để gán quyền hộp thư cá nhân cho mọi nhóm hiện có. Nhóm mới từ UI được gán tự động và quyền mặc định được giữ qua mọi màn hình sửa quyền. Xem mã nguồn, routes, cấu hình và hướng dẫn triển khai tại [docs/ADMIN_TRIGGERED_OTP.md](docs/ADMIN_TRIGGERED_OTP.md).
