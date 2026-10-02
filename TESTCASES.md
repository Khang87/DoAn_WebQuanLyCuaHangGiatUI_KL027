# Test Cases đã chạy thành công

Lần chạy được ghi nhận bằng `php artisan test --compact --testdox` trên cấu hình SQLite in-memory trong `phpunit.xml`.

**Kết quả suite:** 267 test được phát hiện, **75 PASSED**, 192 skipped, 447 assertions. Bảng dưới đây chứa đúng 75 test PASSED, không liệt kê test skipped. Trạng thái mỗi hàng là trạng thái thực tế của test case được PHPUnit/TestDox chạy.

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
| 11 | TC-PRICE-01 — `BookingOrderConversion` | Mức tối thiểu KG được áp dụng riêng trên từng dòng Booking; lưu khối lượng thực và cộng tiền theo từng dòng. | Feature — `tests/Feature/BookingOrderConversionTest.php` | ✅ PASSED |
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
| 65 | TC-REG-39 — `ReportsService` | Route xuất báo cáo chấp nhận bộ lọc toàn thời gian. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 66 | TC-REG-40 — `ReportsService` | Kỳ không có dữ liệu trả cơ cấu rỗng và doanh thu bằng 0. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 67 | TC-REG-41 — `ReportsService` | Xếp hạng dịch vụ theo doanh thu đã thanh toán, kèm số lượng và đơn vị. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 68 | TC-REG-42 — `ReportsService` | Truy vấn xuất báo cáo chỉ lấy hóa đơn thanh toán trong kỳ được chọn. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 69 | TC-REG-43 — `ReportsService` | Tổng hợp phương thức thanh toán chỉ tính giao dịch thành công của hóa đơn đã thanh toán. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 70 | TC-REG-44 — `ReportsService` | Đơn gần đây eager-load khách hàng và chi tiết dịch vụ. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 71 | TC-REG-45 — `ReportsService` | Route xuất báo cáo trả tệp XLSX theo ngày đã chọn. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 72 | TC-REG-46 — `ReportsService` | Từ chối định dạng khoảng ngày không hợp lệ bằng lỗi validation. | Feature — `tests/Feature/ReportsServiceTest.php` | ✅ PASSED |
| 73 | TC-REG-47 — `UserAuthorizationStatus` | Tài khoản không hoạt động không thể dùng quyền hoặc bypass của Chủ cửa hàng. | Unit — `tests/Unit/UserAuthorizationStatusTest.php` | ✅ PASSED |
| 74 | TC-REG-48 — `UserAvatarUrl` | URL avatar dùng đúng ảnh hồ sơ đã lưu. | Unit — `tests/Unit/UserAvatarUrlTest.php` | ✅ PASSED |
| 75 | TC-REG-49 — `UserAvatarUrl` | URL avatar dùng ảnh fallback xác định khi chưa có ảnh hồ sơ. | Unit — `tests/Unit/UserAvatarUrlTest.php` | ✅ PASSED |

### Giới hạn phạm vi kiểm thử

- Suite hiện không có test PASSED độc lập chứng minh quy tắc lấy bản giá có `NgayApDung` mới nhất; đây là logic hiện có trong code nhưng chưa được xác nhận bằng regression test riêng.
- Audit đơn hàng được test trực tiếp qua `OrderService`. Chưa có test tích hợp riêng gọi từng luồng Payment hoặc Dashboard để chứng minh việc ghi audit qua các endpoint đó.
- `KhachHangDiaChiModelTest` kiểm tra ánh xạ bảng, identity và `$fillable`; quan hệ `KhachHang` 1-N với sổ địa chỉ chưa có test assertion độc lập.
- Test SQLite in-memory không xác minh PostgreSQL advisory lock/race-condition thực tế. Không test nào ở đây kết nối hoặc ghi lên Supabase Live.
