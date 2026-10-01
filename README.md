# Sky Laundry

Sky Laundry là hệ thống quản lý cửa hàng giặt ủi, hỗ trợ quy trình vận hành cho Chủ cửa hàng, Quản lý, Nhân viên và Khách hàng. Ứng dụng gồm giao diện web Laravel và API JSON phiên bản hóa, sử dụng cơ sở dữ liệu PostgreSQL trên Supabase theo cấu trúc hiện có.

## Công nghệ sử dụng

- PHP 8.3 trở lên
- Laravel 13.x (`laravel/framework` `^13.17`)
- PostgreSQL trên Supabase
- Blade, giao diện dựa trên Bootstrap, Tailwind CSS 4 và Vite
- PHPUnit 12

Thông tin phiên bản được đối chiếu từ các tệp cấu hình phụ thuộc của dự án; dự án hiện không dùng Laravel 10 hay PHP 8.1.

## Giới thiệu dự án

Sky Laundry hỗ trợ số hóa hoạt động hằng ngày của cửa hàng giặt ủi: tiếp nhận lịch hẹn và đơn hàng, quản lý dịch vụ và bảng giá, theo dõi xử lý/giao nhận, lập hóa đơn, ghi nhận thanh toán và xem báo cáo. Hệ thống có các quyền riêng cho Chủ cửa hàng, Quản lý, Nhân viên và Khách hàng; giao diện web phục vụ vận hành cửa hàng, còn API cung cấp dữ liệu cho các ứng dụng client được cấp quyền.

## Tính năng chính

- Kiểm tra vai trò và quyền tại middleware của route, Gate/controller và giao diện Blade.
- Một tài khoản có thể mang nhiều vai trò qua bảng `TaiKhoan_VaiTro`; quyền vai trò được liên kết qua `VaiTro_Quyen` và `Quyen`.
- Tài khoản không hoạt động (`TaiKhoan.TrangThai`) không được hưởng quyền bỏ qua kiểm tra của Chủ cửa hàng hoặc quyền truy cập theo mã quyền.
- Hỗ trợ nghiệp vụ đơn hàng, đặt lịch, hóa đơn, thanh toán, giao nhận, khách hàng, dịch vụ, bảng giá, khuyến mãi và báo cáo.
- Tính tiền từng dòng theo khối lượng với mức tối thiểu có thể cấu hình, hoặc theo số lượng đối với đơn vị như cái, đôi, bộ, tấm.
- Giữ nguyên cách viết PascalCase của tên bảng và cột PostgreSQL theo schema.
- Có cơ chế bảo vệ các lệnh Artisan có thể phá hủy cấu trúc cơ sở dữ liệu.

## Sơ đồ phân quyền hệ thống

Quyền truy cập được kiểm tra theo vai trò và mã quyền. Việc ẩn nút trên giao diện chỉ hỗ trợ trải nghiệm người dùng; quyết định cho phép hay từ chối vẫn được thực hiện ở tầng route và controller/Gate.

| Chức năng | Chủ cửa hàng | Quản lý | Nhân viên | Khách hàng |
|---|---|---|---|---|
| Quản lý vai trò và ma trận quyền | Toàn quyền | Không được cấp quyền quản lý ma trận | Không được cấp quyền quản lý ma trận | Không được cấp quyền quản lý ma trận |
| Dashboard và báo cáo quản lý | Theo quyền Chủ cửa hàng | Theo quyền được cấp | Chỉ truy cập chức năng được cấp | Bị chặn khỏi khu vực quản trị |
| Đơn hàng, khách hàng, dịch vụ và giao nhận | Theo quyền Chủ cửa hàng | Theo ma trận quyền | Theo ma trận quyền từng thao tác | Bị chặn khỏi giao diện/API quản trị hiện tại |
| Hóa đơn và thanh toán | Theo quyền Chủ cửa hàng | Theo quyền được cấp và quy tắc khóa bản ghi đã quyết toán | Chỉ khi được cấp quyền phù hợp | Bị chặn khỏi giao diện/API quản trị hiện tại |
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
| Báo cáo | Tổng hợp chỉ số vận hành, doanh thu và dữ liệu dịch vụ |
| API | Cung cấp một số danh sách và thao tác đã được xác thực, phân quyền |

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
- Bổ sung xuất Excel `.xlsx` các hóa đơn đã thanh toán theo bộ lọc ngày lập; route và controller đều kiểm tra quyền `reports.view`.
- Giữ thông báo rõ ràng cho chức năng điều kiện đồ giặt chưa được schema hiện tại hỗ trợ, không ghi dữ liệu vào bảng không tồn tại.

### Các vấn đề đã khắc phục

- Sửa các trường hợp tạo URL thiếu tham số và các ánh xạ PascalCase trong những phần đã rà soát.
- Sửa quan hệ trang chi tiết loại đồ giặt để dùng `LoaiDoGiat` và dữ liệu bảng giá đúng theo schema.
- Xóa một số lớp tương thích không còn tham chiếu, mã báo cáo trùng lặp, view không được sử dụng và test mẫu mặc định.
- Khắc phục lỗ hổng phân quyền do dùng chung danh sách quyền OR cho nhiều thao tác và do API thiếu kiểm tra quyền.
- Đồng bộ các truy vấn báo cáo với `HoaDon.ThanhTien`, `HoaDon.NgayLap`, trạng thái thanh toán và trạng thái đặt lịch theo schema hiện tại.
- Chuyển nút Xuất Excel từ giao diện placeholder sang tải báo cáo thực, đồng thời ẩn liên kết Báo cáo với tài khoản thiếu quyền.

### Trạng thái kiểm thử hồi quy

Sau khi bổ sung kiểm thử cho báo cáo, toàn bộ bộ kiểm thử đạt: **225 test, 33 thành công, 192 bị bỏ qua, 285 assertions**. Các test mới kiểm tra KPI, biểu đồ, top dịch vụ, cơ cấu doanh thu, giao dịch thanh toán, đơn hàng gần đây, truy vấn xuất dữ liệu, route xuất Excel và xác thực bộ lọc trên SQLite in-memory. Nhiều test bị bỏ qua là test cũ dựa trên schema tiếng Anh trước đây; test bị bỏ qua không được tính là độ bao phủ đã xác minh.

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

- Kiểm thử Feature/Unit tập trung cho đợt chuẩn hóa: **51/51 test thành công, 346 assertions**. Đây là kết quả của bộ kiểm thử phạm vi refactor, tách biệt với thống kê toàn bộ hồi quy ở mục trên; các test legacy bị bỏ qua không được tính là test pass.
- Các bước xác minh của đợt refactor bao gồm biên dịch Blade, định dạng Laravel Pint và kiểm tra thay đổi Git.
- Kiểm thử dùng SQLite trong bộ nhớ. `schema.sql` chỉ là snapshot tham chiếu; không chạy DDL, migration hoặc thao tác ghi dữ liệu thử nghiệm lên Supabase Live.

### Công việc dự kiến

- Tiếp tục đối chiếu các ánh xạ của `DonHang`, `ChiTietDonHang` và `HoaDon` với snapshot mới nhất trong `schema.sql` và truy vấn thực tế.
- Kiểm thử luồng tính tiền lúc tạo/sửa đơn, bao gồm dữ liệu null, bằng 0 và khối lượng tối thiểu.
- Chỉ xây dựng cổng Khách hàng xem đơn khi mọi truy vấn đều giới hạn theo `KhachHangID` của tài khoản đang đăng nhập.
- Giảm số test legacy đang bị bỏ qua và bổ sung kiểm thử trên schema được hỗ trợ, không kết nối test tới Supabase Live.

## Kiến trúc và nguyên tắc an toàn dữ liệu

- **Chỉ đọc DDL:** Coi `schema.sql` là snapshot tham chiếu. Không sửa file này, không chạy migration và không thực thi lệnh DDL trên Supabase.
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
php artisan test
```

Liệt kê các route đã đăng ký; lệnh này không thay đổi cơ sở dữ liệu:

```sh
php artisan route:list
```
