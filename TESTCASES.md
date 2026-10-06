# Test Cases và kết quả kiểm thử

Lần chạy được ghi nhận bằng `php artisan test --compact` trên cấu hình SQLite in-memory trong `phpunit.xml`.

**Kết quả suite đầy đủ đã ghi nhận trước đó:** 311 test được phát hiện, **119 PASSED**, 192 skipped, 624 assertions. Test chạy trên SQLite in-memory theo `phpunit.xml`; các test skipped không được tính là passed và không có test nào ghi lên Supabase Live. Các bảng dưới đây ghi các kịch bản regression trọng tâm; kết quả kiểm thử mới được bổ sung riêng, không thay thế số liệu suite đầy đủ nếu chưa chạy lại toàn bộ PHPUnit.

## Nhóm 1: Booking & Order Conversion

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 1 | TC-BO-01 — `BookingOrderConversion` | Xác nhận Booking bị rollback khi không có snapshot dịch vụ; không để lại trạng thái xác nhận hoặc đơn/giao nhận dở dang. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 2 | TC-BO-02 — `BookingOrderConversion` | Không cho hủy hoặc xóa riêng Booking đã có đơn hàng liên kết. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 3 | TC-BO-03 — `BookingOrderConversion` | Request từ chối nhập số lượng cho đơn vị KG và từ chối gửi đồng thời số lượng lẫn khối lượng. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 4 | TC-BO-04 — `BookingOrderConversion` | Request chấp nhận khối lượng dương cho đơn vị tính theo trọng lượng. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 5 | TC-BO-05 — `BookingOrderConversion` | Request yêu cầu trường số lượng/khối lượng phù hợp với đơn vị đã chọn. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 6 | TC-BO-06 — `BookingOrderConversion` | Từ chối chuyển đổi khi snapshot khối lượng/số lượng không khớp loại đơn vị. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |

## Nhóm 2: Audit Logging

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 7 | TC-AUD-01 — `BookingOrderConversion` | Xác nhận Booking tạo đơn, chi tiết và lịch giao; kiểm tra audit ghi nhân viên xác nhận (`NhanVienXacNhanID`) cùng thời điểm (`ThoiGianXacNhan`). | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 8 | TC-AUD-02 — `BookingOrderConversion` | Tạo Booking ghi sự kiện vào `NhatKyHeThong` với mã bản ghi, người thao tác và trạng thái mới. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 9 | TC-AUD-03 — `BookingOrderConversion` | Đổi trạng thái đơn qua `OrderService` ghi trạng thái cũ/mới và người thao tác vào audit. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 10 | TC-AUD-04 — `UserAccountAudit` | Tạo/sửa tài khoản và đổi mật khẩu được audit; snapshot không ghi trường/mật khẩu bí mật. | Feature — `tests/Feature/UserAccountAuditTest.php` | ✅ PASSED |

## Nhóm 3: Pricing Engine & Rules

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 11 | TC-PRICE-01 — `BookingOrderConversion` | Mức tối thiểu 3kg áp dụng riêng cho từng dòng Booking (1.5kg tính 3kg, 4kg tính 4kg); lưu khối lượng thực và cộng đúng tiền. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 12 | TC-PRICE-02 — `BookingOrderConversion` | Đơn hàng lấy giá từ đúng tuple dịch vụ/loại đồ/đơn vị, không tin đơn giá gửi từ client. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 13 | TC-PRICE-03 — `BookingOrderConversion` | Từ chối tạo đơn khi thiếu bảng giá đúng tuple ba khóa. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 14 | TC-PRICE-04 — `BookingOrderConversion` | Từ chối khoảng hiệu lực chồng lấn trên cùng tuple; cho phép khoảng kế tiếp không giao nhau và sửa bản giá không tự chồng lấn. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 15 | TC-PRICE-05 — `TinhTienGiatUiService` | Dòng KG áp mức tối thiểu nhưng không nhân khối lượng bởi số lượng. | Unit — `tests/Unit/TinhTienGiatUiServiceTest.php` | ✅ PASSED |
| 16 | TC-PRICE-06 — `TinhTienGiatUiService` | Nhận diện đơn vị KG không phân biệt hoa thường và dùng khối lượng thực khi vượt mức tối thiểu. | Unit — `tests/Unit/TinhTienGiatUiServiceTest.php` | ✅ PASSED |
| 17 | TC-PRICE-07 — `TinhTienGiatUiService` | Đơn vị không theo trọng lượng tính tiền theo số lượng thay vì khối lượng. | Unit — `tests/Unit/TinhTienGiatUiServiceTest.php` | ✅ PASSED |
| 18 | TC-PRICE-08 — `TinhTienGiatUiService` | Giữ số lượng thập phân của cột numeric cho đơn vị không theo trọng lượng. | Unit — `tests/Unit/TinhTienGiatUiServiceTest.php` | ✅ PASSED |
| 19 | TC-PRICE-09 — `TinhTienGiatUiService` | Mức tối thiểu truyền riêng trên dòng ghi đè cấu hình mặc định. | Unit — `tests/Unit/TinhTienGiatUiServiceTest.php` | ✅ PASSED |
| 20 | TC-PRICE-10 — `TinhTienGiatUiService` | Tổng hóa đơn cộng các thành tiền dòng đã được làm tròn. | Unit — `tests/Unit/TinhTienGiatUiServiceTest.php` | ✅ PASSED |

## Nhóm 4: Database Models & Identity

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 21 | TC-DB-01 — `KhachHangDiaChiModel` | Model ánh xạ bảng/cột lowercase, dùng `diachiid` làm khóa tăng tự động và không cho mass-assign identity key. | Unit — `tests/Unit/KhachHangDiaChiModelTest.php` | ✅ PASSED |
| 22 | TC-DB-02 — `KhuyenMaiSchemaCompatibility` | Model khuyến mãi khớp bảng và khóa chính trong schema hiện hành. | Unit — `tests/Unit/KhuyenMaiSchemaCompatibilityTest.php` | ✅ PASSED |
| 23 | TC-DB-03 — `KhuyenMaiSchemaCompatibility` | Số lượt sử dụng không bị diễn giải thành giới hạn đổi mã. | Unit — `tests/Unit/KhuyenMaiSchemaCompatibilityTest.php` | ✅ PASSED |
| 24 | TC-DB-04 — `LoaiDoGiat` | Model loại đồ giặt dùng PascalCase theo schema và khai báo quan hệ khóa ngoại. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 25 | TC-DB-05 — `OrderStatusSchema` | Giá trị trạng thái trên dropdown khớp constraint trạng thái đơn hàng. | Unit — `tests/Unit/OrderStatusSchemaTest.php` | ✅ PASSED |
| 26 | TC-DB-06 — `OrderStatusSchema` | Xác nhận schema dùng `NhatKyHeThong` thay bảng lịch sử trạng thái legacy. | Unit — `tests/Unit/OrderStatusSchemaTest.php` | ✅ PASSED |

## Nhóm 5: Các kiểm thử hồi quy khác

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 27 | TC-REG-01 — `AuthorizationBoundary` | Khách hàng không truy cập được route quản trị. | Feature — `tests/Feature/AuthorizationBoundaryTest.php` | ✅ PASSED |
| 28 | TC-REG-02 — `AuthorizationBoundary` | Khách hàng không truy cập được API đơn hàng. | Feature — `tests/Feature/AuthorizationBoundaryTest.php` | ✅ PASSED |
| 29 | TC-REG-03 — `AuthorizationBoundary` | Nhân viên chỉ có quyền xem không thể tạo khách hàng. | Feature — `tests/Feature/AuthorizationBoundaryTest.php` | ✅ PASSED |
| 30 | TC-REG-04 — `AuthorizationBoundary` | API danh sách đơn yêu cầu quyền xem đơn hàng. | Feature — `tests/Feature/AuthorizationBoundaryTest.php` | ✅ PASSED |
| 31 | TC-REG-05 — `AuthorizationBoundary` | Nhân viên thiếu quyền xóa đơn bị từ chối. | Feature — `tests/Feature/AuthorizationBoundaryTest.php` | ✅ PASSED |
| 32 | TC-REG-06 — `AuthorizationBoundary` | Resource routes kiểm tra quyền theo từng hành động. | Feature — `tests/Feature/AuthorizationBoundaryTest.php` | ✅ PASSED |
| 33 | TC-REG-07 — `CustomerSort` | Khách hàng thiếu bản ghi điểm được sắp xếp như có 0 điểm. | Feature — `tests/Feature/CustomerSortTest.php` | ✅ PASSED |
| 34 | TC-REG-08 — `CustomerSort` | Sắp xếp tăng dần ổn định khi khách hàng có tổng điểm bằng nhau. | Feature — `tests/Feature/CustomerSortTest.php` | ✅ PASSED |
| 35 | TC-REG-09 — `LoaiDoGiat` | Danh mục loại đồ giặt có thể tạo, liệt kê, sửa và xóa. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 36 | TC-REG-10 — `LoaiDoGiat` | Loại đồ đã được bảng giá/đơn hàng/Booking tham chiếu sẽ bị vô hiệu hóa thay vì xóa. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 37 | TC-REG-11 — `LoaiDoGiat` | Tên loại đồ giặt phải duy nhất. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 38 | TC-REG-12 — `LoaiDoGiat` | Nhân viên có quyền danh mục có thể tạo và đổi trạng thái loại đồ. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 39 | TC-REG-13 — `LoaiDoGiat` | Chủ cửa hàng có quyền phù hợp có thể tạo danh mục. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 40 | TC-REG-14 — `LoaiDoGiat` | Nhân viên thiếu quyền cần thiết không thể quản lý danh mục. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 41 | TC-REG-15 — `LoaiDoGiat` | Vai trò Quản lý không tự có quyền danh mục chỉ nhờ tên vai trò. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 42 | TC-REG-16 — `LoaiDoGiat` | Resource danh mục kết hợp kiểm tra vai trò và quyền theo hành động. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 43 | TC-REG-17 — `LoaiDoGiat` | Trang loại đồ legacy chuyển về module canonical duy nhất. | Feature — `tests/Feature/LoaiDoGiatTest.php` | ✅ PASSED |
| 44 | TC-REG-18 — `LuuDonHangRequest` | Khi sửa đơn, chấp nhận mã đơn hiện tại của chính đơn đó. | Feature — `tests/Feature/LuuDonHangRequestTest.php` | ✅ PASSED |
| 45 | TC-REG-19 — `LuuDonHangRequest` | Từ chối mã đơn đã được một đơn hàng khác sử dụng. | Feature — `tests/Feature/LuuDonHangRequestTest.php` | ✅ PASSED |
| 46 | TC-REG-20 — `ProfileUpdate` | Cập nhật hồ sơ nhân viên đồng bộ tài khoản và hồ sơ nhân viên. | Feature — `tests/Feature/ProfileUpdateTest.php` | ✅ PASSED |
| 47 | TC-REG-21 — `ProfileUpdate` | Cập nhật hồ sơ khách hàng đồng bộ tài khoản và hồ sơ khách hàng. | Feature — `tests/Feature/ProfileUpdateTest.php` | ✅ PASSED |
| 48 | TC-REG-22 — `ProfileUpdate` | Từ chối email hồ sơ đã được tài khoản khác sử dụng. | Feature — `tests/Feature/ProfileUpdateTest.php` | ✅ PASSED |
| 49 | TC-REG-23 — `QuyenMapper` | Ánh xạ mã quyền nội bộ tới `MaQuyen` tương ứng. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 50 | TC-REG-24 — `QuyenMapper` | Dùng quyền quản lý làm fallback khi thiếu quyền hành động cụ thể. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 51 | TC-REG-25 — `QuyenMapper` | Ánh xạ module chỉ có một `MaQuyen`. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 52 | TC-REG-26 — `QuyenMapper` | Quyền xem/sửa thanh toán dùng quyền tạo; quyền xóa không được suy diễn. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 53 | TC-REG-27 — `QuyenMapper` | Mã không có `MaQuyen` tương ứng không được phân giải. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 54 | TC-REG-28 — `QuyenMapper` | `covers` là phép kiểm tra ngược tương thích với `resolveMaQuyen`. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 55 | TC-REG-29 — `QuyenMapper` | Mã quản lý bao phủ các hành động trong module. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 56 | TC-REG-30 — `QuyenMapper` | Quyền toàn hệ thống cho phép các hành động tương ứng. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 57 | TC-REG-31 — `QuyenMapper` | Quyền tài chính và RBAC được giới hạn cho Chủ cửa hàng. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 58 | TC-REG-32 — `QuyenMapper` | Nhận diện quyền DB chỉ dành cho Chủ cửa hàng. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 59 | TC-REG-33 — `QuyenMapper` | Mọi mã quyền nội bộ được ánh xạ tới `MaQuyen` đã biết. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 60 | TC-REG-34 — `QuyenMapper` | Phát hiện `MaQuyen` đã biết nhưng chưa được mã nội bộ sử dụng. | Unit — `tests/Unit/QuyenMapperTest.php` | ✅ PASSED |
| 61 | TC-REG-35 — `ReportsService` | KPI dùng doanh thu hóa đơn đã thanh toán và đếm Booking mới. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 62 | TC-REG-36 — `ReportsService` | Biểu đồ dùng ngày hóa đơn đã thanh toán và tính tỷ trọng dịch vụ. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 63 | TC-REG-37 — `ReportsService` | Bộ lọc ngày tùy chỉnh xử lý ngày lịch Việt Nam với timestamp UTC. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 64 | TC-REG-38 — `ReportsService` | Bộ lọc toàn thời gian lấy dữ liệu cũ và nhóm biểu đồ theo tháng. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 65 | TC-REG-50 — `ReportsService` | KPI dùng doanh thu hóa đơn đã thanh toán và đếm Booking mới trong khoảng ngày. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 66 | TC-REG-51 — `ReportsService` | Kỳ không có dữ liệu trả cơ cấu rỗng và doanh thu bằng 0. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 67 | TC-REG-52 — `ReportsService` | Xếp hạng dịch vụ theo doanh thu đã thanh toán, kèm số lượng và đơn vị. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 68 | TC-REG-53 — `ReportsService` | Tổng hợp phương thức thanh toán chỉ tính giao dịch thành công của hóa đơn đã thanh toán. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 69 | TC-REG-54 — `ReportsService` | Đơn gần đây eager-load khách hàng và chi tiết dịch vụ để tránh N+1. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 70 | TC-REG-55 — `ReportsService` | Từ chối định dạng khoảng ngày không hợp lệ bằng lỗi validation. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 73 | TC-REG-47 — `UserAuthorizationStatus` | Tài khoản không hoạt động không thể dùng quyền hoặc bypass của Chủ cửa hàng. | Unit — `tests/Unit/UserAuthorizationStatusTest.php` | ✅ PASSED |
| 74 | TC-REG-48 — `UserAvatarUrl` | URL avatar dùng đúng ảnh hồ sơ đã lưu. | Unit — `tests/Unit/UserAvatarUrlTest.php` | ✅ PASSED |
| 75 | TC-REG-49 — `UserAvatarUrl` | URL avatar dùng ảnh fallback xác định khi chưa có ảnh hồ sơ. | Unit — `tests/Unit/UserAvatarUrlTest.php` | ✅ PASSED |

### Ca kiểm thử bổ sung trong đợt regression

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 76 | TC-PRICE-11 — `BookingOrderConversion` | Tra cứu chọn giá có ngày áp dụng mới nhất, bao gồm đúng ngày bắt đầu/kết thúc và loại trừ giá tương lai. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 77 | TC-DB-07 — `KhachHangDiaChiModel` | Quan hệ `KhachHang` 1-N dùng khóa ngoại lowercase `khachhangid` và đúng bảng địa chỉ. | Unit — `tests/Unit/KhachHangDiaChiModelTest.php` | ✅ PASSED |
| 78 | TC-PRICE-12 — `PricingDateInput` | Chuẩn hóa ngày nhập `dd-mm-yyyy` thành định dạng ngày hợp lệ trước validation. | Feature — `tests/Feature/PricingDateInputTest.php` | ✅ PASSED |
| 79 | TC-PRICE-13 — `PricingDateInput` | Từ chối ngày `dd-mm-yyyy` không tồn tại, không tự biến ngày sai thành ngày hợp lệ. | Feature — `tests/Feature/PricingDateInputTest.php` | ✅ PASSED |
| 80 | TC-BK-07 — `BookingOrderConversion` | Rollback trạng thái xác nhận, audit, đơn hàng và giao nhận khi tạo chi tiết đơn thất bại giữa transaction. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 81 | TC-BK-08 — `BookingOrderConversion` | Từ chối khối lượng âm/0 và số lượng 0 ở đơn vị bắt buộc có giá trị dương. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 82 | TC-BK-09 — `BookingOrderConversion` | Làm tròn khối lượng thừa độ chính xác về 2 chữ số trước khi lưu chi tiết Booking. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 83 | TC-BK-10 — `BookingOrderConversion` | Làm tròn khối lượng trước tính tiền/lưu đơn và vẫn lưu khối lượng thực thay vì mức tối thiểu tính phí. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 84 | TC-BK-11 — `BookingOrderConversion` | Request đơn hàng làm tròn khối lượng về 2 chữ số trước validation. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 85 | TC-UI-01 — `MeasurementFormatting` | Hiển thị số lượng dạng số nguyên, khối lượng đúng hai chữ số và định dạng kết hợp `2 món · 16.00 kg`. | Unit — `tests/Unit/MeasurementFormattingTest.php` | ✅ PASSED |
| 86 | TC-BK-12 — `BookingOrderConversion` | Gọi route xác nhận từ danh sách chuyển Booking đang chờ thành Đã xác nhận và tạo đúng một đơn cùng phiếu giao. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 87 | TC-BK-13 — `BookingOrderConversion` | Booking đã hủy không được xác nhận và không sinh đơn hàng. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 88 | TC-BK-14 — `BookingOrderConversion` | Booking chưa có dòng dịch vụ được chuyển tới form sửa với thông báo hướng dẫn; trạng thái và đơn hàng không bị thay đổi. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |

### Kiểm thử tối ưu hiệu năng và luồng nghiệp vụ mới

Các lần chạy dưới đây là kiểm thử hẹp trên SQLite in-memory, không kết nối Supabase Live.

| Mã kiểm tra | Test | Kịch bản đã xác minh | Kết quả |
|---|---|---|---|
| TC-PERF-01 | `ReportsServiceTest::test_kpis_and_revenue_chart_use_bounded_aggregate_query_counts` | KPI dùng số truy vấn cố định cho phần tổng hợp trạng thái; biểu đồ doanh thu dùng một truy vấn tổng hợp. | ✅ PASSED |
| TC-PERF-02 | `DashboardRevenueChartTest::test_revenue_chart_aggregates_each_filter_in_a_single_query` | Các bộ lọc hôm nay/7 ngày/tháng/năm đều chạy một truy vấn tổng hợp và trả đúng số liệu theo bucket. | ✅ PASSED |
| TC-PERF-03 | `ReportsServiceTest::test_recent_orders_eager_load_customer_and_service_details` | Danh sách đơn gần đây nạp sẵn khách hàng và dịch vụ trong chi tiết đơn. | ✅ PASSED |
| TC-PAY-01 | `PaymentOrderRedirectTest` | Kiểm tra số tiền còn phải thu, chặn thanh toán vượt số dư, transaction code duy nhất và không bỏ qua vòng đời đơn. | ✅ PASSED |
| TC-POINT-08 | `RewardPointTest::test_repeated_order_edits_only_adjust_the_difference_in_redeemed_points` | Lưu/sửa cùng đơn nhiều lần chỉ điều chỉnh phần điểm chênh lệch, không trừ lặp toàn bộ. | ✅ PASSED |
| TC-COMM-07 | `AdminCommunicationTest` | Tiêu đề thông báo được lưu, thời gian gửi lấy từ server, gửi theo nhóm người nhận và đánh dấu đã đọc chỉ trên tài khoản hiện tại. | ✅ PASSED |
| TC-COMM-08 | `PromotionNotificationTest::test_creating_active_promotion_does_not_fan_out_database_notifications` | Tạo khuyến mãi không phát sinh thông báo database hàng loạt ngoài ý muốn. | ✅ PASSED |

Lần chạy tập trung xác nhận tối ưu báo cáo: `php artisan test --compact tests/Feature/DashboardRevenueChartTest.php tests/Feature/ReportsServiceTest.php` — **11 passed, 70 assertions**.

### Kiểm thử Nhật ký hệ thống (SystemLog)

Chạy riêng bằng `php artisan test --compact --filter=SystemLogTest`: **4 passed**, 11 assertions.

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 89 | TC-SLOG-01 — `SystemLog` | Lọc nhật ký theo dropdown `TaiKhoanID` chỉ trả về log thuộc tài khoản được chọn. | Feature — `tests/Feature/SystemLogTest.php` | ✅ PASSED |
| 90 | TC-SLOG-02 — `SystemLog` | Nhật ký có `TaiKhoanID` NULL được hiển thị với nhãn “Hệ thống”. | Feature — `tests/Feature/SystemLogTest.php` | ✅ PASSED |
| 91 | TC-SLOG-03 — `SystemLog` | View nhận danh sách tài khoản chỉ gồm `TaiKhoanID`, `TenDangNhap` và hiển thị option dropdown đúng định dạng. | Feature — `tests/Feature/SystemLogTest.php` | ✅ PASSED |
| 92 | TC-SLOG-04 — `SystemLog` | Tài khoản vai trò Nhân viên bị từ chối truy cập route Nhật ký hệ thống (HTTP 403). | Feature — `tests/Feature/SystemLogTest.php` | ✅ PASSED |

### Kiểm thử Điểm tích lũy (RewardPoint)

Chạy riêng bằng `php artisan test --compact --filter=RewardPointTest`: **7 passed**, 26 assertions.

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 93 | TC-POINT-01 — `RewardPoint` | Đơn 100.000 VNĐ chuyển sang “Đã giao” cộng 10.000 điểm; đổi trạng thái qua lại không cộng trùng. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |
| 94 | TC-POINT-02 — `RewardPoint` | Xác nhận Booking dùng 100 điểm, giảm 1.000 VNĐ trên đơn 10.000 VNĐ và trừ đúng số dư khách hàng. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |
| 95 | TC-POINT-03 — `RewardPoint` | Tạo đơn dùng 500 điểm giảm 5.000 VNĐ; số dư điểm bị trừ đúng 500. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |
| 96 | TC-POINT-04 — `RewardPoint` | Khi sửa đơn sang khách khác, hoàn điểm đã dùng về khách cũ và trừ điểm khách mới theo tỷ lệ 10 VNĐ/điểm. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |
| 97 | TC-POINT-05 — `RewardPoint` | Giới hạn số điểm sử dụng theo số dư và phần tiền còn lại; xác nhận 100 điểm giảm 1.000 VNĐ. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |
| 98 | TC-POINT-06 — `RewardPoint` | Từ chối tạo đơn khi điểm yêu cầu vượt số dư; đơn không được lưu và số dư giữ nguyên. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |
| 99 | TC-POINT-07 — `RewardPoint` | Khi sửa đơn thất bại do khách mới không đủ điểm, rollback cả hoàn điểm cho khách cũ và các thay đổi đơn hàng. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED |

### Kiểm thử giao tiếp quản trị, chat và audit tài khoản

Chạy riêng: `php artisan test --compact --filter=AdminCommunicationTest` (**6 passed**, 31 assertions) và `php artisan test --compact --filter=UserAccountAuditTest` (**1 passed**, 18 assertions).

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 100 | TC-COMM-01 — `AdminCommunication` | Tin nhắn cửa hàng được gửi tới tài khoản gắn với khách hàng của đơn, chuẩn hóa nội dung và không tạo system audit. | Feature — `tests/Feature/AdminCommunicationTest.php` | ✅ PASSED |
| 101 | TC-COMM-02 — `AdminCommunication` | Từ chối gửi tin nhắn khi khách hàng của đơn chưa có tài khoản nhận; không tạo bản ghi `TinNhan`. | Feature — `tests/Feature/AdminCommunicationTest.php` | ✅ PASSED |
| 102 | TC-COMM-03 — `AdminCommunication` | Lọc nhật ký kết hợp bảng, hành động, `TaiKhoanID` và khoảng ngày. | Feature — `tests/Feature/AdminCommunicationTest.php` | ✅ PASSED |
| 103 | TC-COMM-04 — `AdminCommunication` | Chủ cửa hàng truy cập được Nhật ký hệ thống và có liên kết menu tương ứng. | Feature — `tests/Feature/AdminCommunicationTest.php` | ✅ PASSED |
| 104 | TC-COMM-05 — `AdminCommunication` | Quản lý và Nhân viên bị chặn khỏi Nhật ký hệ thống; route nhắn tin quản trị vẫn truy cập được. | Feature — `tests/Feature/AdminCommunicationTest.php` | ✅ PASSED |
| 105 | TC-COMM-06 — `AdminCommunication` | Route Nhật ký yêu cầu đồng thời vùng vai trò quản trị và vai trò Chủ cửa hàng. | Feature — `tests/Feature/AdminCommunicationTest.php` | ✅ PASSED |
| 106 | TC-AUD-05 — `UserAccountAudit` | Tạo/cập nhật tài khoản và đổi mật khẩu được ghi audit mà không lưu mật khẩu vào snapshot. | Feature — `tests/Feature/UserAccountAuditTest.php` | ✅ PASSED |

## Nhóm 6: Loại bỏ Cài đặt hệ thống

Các mục dưới đây là checklist hồi quy cho thay đổi gỡ tính năng. Đây là kiểm tra thủ công, không thuộc kết quả PHPUnit gần nhất.

| Mã kiểm tra | Kịch bản | Kết quả mong đợi | Trạng thái |
|---|---|---|---|
| TC-SET-01 | Mở menu tài khoản trên thanh điều hướng. | Không còn liên kết “Cài đặt” hoặc đường dẫn `/settings`. | ⏳ NOT RUN |
| TC-SET-02 | Truy cập trực tiếp `/settings`. | Không còn route cài đặt được đăng ký; ứng dụng trả về trang không tìm thấy. | ⏳ NOT RUN |
| TC-SET-03 | Mở trang hồ sơ cá nhân. | Trang hồ sơ vẫn hiển thị, không có liên kết tới trang Cài đặt đã gỡ bỏ. | ⏳ NOT RUN |
| TC-SET-04 | Tra cứu quyền hệ thống liên quan đến cấu hình. | Không còn mã quyền `settings.view` trong registry hoặc ánh xạ quyền. | ⏳ NOT RUN |

## Nhóm 7: Cập nhật Booking, điểm tích lũy và giao diện quản trị (06/10/2026)

Các test tự động bên dưới dùng SQLite in-memory. Kết quả gần nhất của `BookingOrderConversionTest` là **35 passed, 180 assertions**. Các ca điểm tích lũy được xác nhận trong lần chạy kết hợp Booking/RewardPoint trước đó (**46 tests, 215 assertions**); suite tổng thể chưa được chạy lại sau các cập nhật này.

| STT | Mã Test / Tên Class Test | Mô tả kịch bản test | Môi trường/File test | Trạng thái |
|---:|---|---|---|---|
| 107 | TC-BOOKING-STAFF-01 — `BookingOrderConversion` | Không cho xác nhận Booking/tạo đơn nếu chưa có nhân viên phụ trách hợp lệ. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 108 | TC-BOOKING-STAFF-02 — `BookingOrderConversion` | Từ chối thay nhân viên phụ trách khi Booking đã liên kết đơn hàng. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
| 109 | TC-POINT-08 — `RewardPoint` | Khi bật công tắc ở form tạo đơn, tự dùng số điểm tối đa trong giới hạn số dư và số tiền còn phải trả. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED — lần chạy kết hợp |
| 110 | TC-POINT-09 — `RewardPoint` | Khi tắt công tắc ở form tạo đơn, không trừ điểm khách hàng. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED — lần chạy kết hợp |
| 111 | TC-POINT-10 — `RewardPoint` | Khi bật công tắc xác nhận Booking, áp dụng toàn bộ điểm khả dụng nhưng không vượt số tiền đơn. | Feature — `tests/Feature/RewardPointTest.php` | ✅ PASSED — lần chạy kết hợp |
| 112 | TC-BOOKING-UI-01 — giao diện sửa Booking | Đổi danh mục làm lọc lại dịch vụ và đơn vị tính; danh mục rỗng hiển thị thông báo và khóa ô dịch vụ. | Kiểm tra giao diện trình duyệt | ⚠️ Đã kiểm tra lọc danh mục có dịch vụ; chưa xác minh riêng nhánh danh mục rỗng |
| 113 | TC-BOOKING-UI-02 — giao diện sửa Booking | Trường nhân viên bị khóa và nền xám sau khi Booking đã có đơn; backend vẫn từ chối request đổi nhân viên. | Kiểm tra trình duyệt + Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ Đã kiểm tra UI và test backend |
| 114 | TC-PROMO-UI-01 — giao diện khuyến mãi | Loại giảm cố định vô hiệu hóa/làm xám mức giảm tối đa; loại phần trăm bật lại trường này. | Kiểm tra giao diện tạo/sửa khuyến mãi | ⏳ NOT RUN trong lần cập nhật tài liệu |
| 115 | TC-PROFILE-AVATAR-01 — `ProfileUpdate` | Signed upload URL tải ảnh lên Supabase Storage; chỉ lưu URL sau khi xác minh object, và từ chối đường dẫn thuộc tài khoản khác. | Feature — `tests/Feature/ProfileUpdateTest.php` | ✅ PHPUnit cục bộ: 13 test / 57 assertions cùng `UserAvatarUrlTest`; không phải kiểm thử production |
| 116 | TC-CUSTOMER-SORT-01 — `CustomerSort` | Sắp xếp khách theo điểm, xem khách không có điểm như 0 và giữ thứ tự ổn định khi bằng điểm. | Feature — `tests/Feature/CustomerSortTest.php` | ⏳ Có test; chưa chạy trong lần cập nhật tài liệu |
| 117 | TC-SCHEMA-RO-01 — Snapshot Supabase | Đối chiếu snapshot `schema.sql` với metadata catalog chỉ đọc; không thực thi DDL, migration hoặc ghi dữ liệu. | Đối chiếu metadata PostgreSQL, ngày 06/10/2026 | ✅ Đã đối chiếu; không phải PHPUnit test |

### Kiểm tra production — Avatar

Domain production: [https://do-an-web-quan-ly-cua-hang-giat-ui.vercel.app](https://do-an-web-quan-ly-cua-hang-giat-ui.vercel.app).

| Mã kiểm tra | Kịch bản | Kết quả |
|---|---|---|
| TC-PROD-AVATAR-01 | Mở trang đăng nhập production và kiểm tra route hồ sơ được bảo vệ. | ✅ `/login` trả HTTP 200; truy cập `/profile` chưa đăng nhập được chuyển hướng về `/login`. |
| TC-PROD-AVATAR-02 | Đăng nhập tài khoản test, xin signed URL cho bucket `avatars`, upload ảnh và xác minh ảnh/URL đã lưu. | 🟡 Sau deploy `e6ec88f` ngày 06/10/2026: trang hồ sơ hiển thị bucket `avatars`; endpoint xin signed upload URL trả HTTP 200 và có token. Đã xác minh cấu hình bucket và bước ký URL tới Supabase. Không gửi ảnh/không lưu URL để tránh ghi Storage object hoặc dữ liệu hồ sơ Supabase; vì vậy upload tệp và lưu avatar end-to-end vẫn chưa được xác minh. |

### Giới hạn phạm vi kiểm thử

- Audit đơn hàng được test trực tiếp qua `OrderService`. Chưa có test tích hợp riêng gọi từng luồng Payment hoặc Dashboard để chứng minh việc ghi audit qua các endpoint đó.
- Test SQLite in-memory kiểm chứng nhánh nghiệp vụ nhưng không chạy advisory transaction lock PostgreSQL và không xác minh race-condition/deadlock dưới tải đồng thời. Không test nào ở đây kết nối hoặc ghi lên Supabase Live.
- Kiểm tra production xác nhận route hồ sơ dùng bucket `avatars` và Supabase ký signed upload URL thành công (HTTP 200). Không gửi bytes ảnh hoặc gọi bước lưu avatar; không tạo object, cập nhật hồ sơ, hay thay đổi schema/bucket/dữ liệu Supabase. PHPUnit cục bộ không thay thế kiểm thử tích hợp upload end-to-end.
- Chưa có class riêng tên `ChatAuthorizationTest`; kiểm thử giao tiếp hiện nằm trong `AdminCommunicationTest`. Các ca hiện có kiểm tra route quản trị cho Quản lý/Nhân viên và gửi tới tài khoản khách hàng liên kết, nhưng chưa kiểm thử việc khách hàng đăng nhập không thể đọc hội thoại đơn khác hoặc badge UI theo vai trò. Giao diện hiện phân biệt người gửi bằng nhãn “Cửa hàng”/tên tài khoản.
