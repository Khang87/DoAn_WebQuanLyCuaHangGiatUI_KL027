# TÀI LIỆU NGỮ CẢNH DỰ ÁN
# Hệ thống quản lý cửa hàng giặt ủi

> File này là tài liệu ngữ cảnh chính dành cho thành viên nhóm và AI khi đọc, phân tích,
> sửa đổi hoặc phát triển source code của dự án.
>
> Khi có mâu thuẫn giữa source code hiện tại và tài liệu này:
> - Source code phản ánh trạng thái triển khai hiện tại.
> - Tài liệu này phản ánh định hướng nghiệp vụ và kiến trúc đã thống nhất của đồ án.
> - Không tự ý thay đổi nghiệp vụ đã chốt nếu chưa có yêu cầu rõ ràng.
> - Không lấy nghiệp vụ của các dự án khác làm cơ sở cho dự án này.

---

# 1. THÔNG TIN DỰ ÁN

## 1.1. Tên đề tài

**Xây dựng ứng dụng quản lí cửa hàng giặt ủi**

## 1.2. Loại dự án

Đồ án tốt nghiệp ngành Công nghệ thông tin.

Nhóm gồm 3 sinh viên năm 4.

## 1.3. Mục tiêu

Xây dựng hệ thống hỗ trợ cửa hàng giặt ủi quản lý các nghiệp vụ:

- Quản lý khách hàng
- Quản lý dịch vụ
- Quản lý loại đồ giặt
- Quản lý bảng giá
- Tiếp nhận và quản lý đơn giặt ủi
- Theo dõi quá trình xử lý đơn
- Quản lý giao nhận
- Quản lý thanh toán
- Quản lý hóa đơn
- Quản lý khuyến mãi
- Quản lý điểm tích lũy
- Quản lý tài khoản và vai trò
- Thông báo
- Đánh giá
- Theo dõi hoạt động kinh doanh
- Báo cáo

Hệ thống dự kiến gồm:

1. Website dành cho Quản lý/Nhân viên.
2. Ứng dụng mobile dành chủ yếu cho Khách hàng.
3. Backend/API dùng chung.
4. Cơ sở dữ liệu MySQL.

---

# 2. PHẠM VI VÀ NGUYÊN TẮC QUAN TRỌNG

## 2.1. Đây là dự án giặt ủi

Không được nhầm dự án này với các dự án khác của người dùng, đặc biệt:

- Không sử dụng nghiệp vụ của dự án BA HOTELNT.
- Không sử dụng SRS HOTELNT làm yêu cầu của dự án này.
- Không tự đưa các chức năng của HOTELNT vào hệ thống giặt ủi.

## 2.2. Nguyên tắc về nghiệp vụ

Nghiệp vụ phải dựa trên quy trình thực tế của cửa hàng giặt ủi và các yêu cầu đã được nhóm thống nhất.

Không tự ý giả định một nghiệp vụ chỉ vì nó phổ biến ở các hệ thống khác.

Nếu một nghiệp vụ chưa được chốt:

- Ghi rõ `TBD`.
- Không tự biến nó thành yêu cầu bắt buộc.
- Khi tư vấn, phải chỉ ra đây là điểm chưa chốt.

## 2.3. Phân biệt prototype và hệ thống chính thức

Source code hiện tại của repository chủ yếu là:

**UI Prototype / Demo**

Không được xem source code hiện tại là thiết kế database cuối cùng.

Một số module hiện tại đang sử dụng:

- PHP thuần
- JSON file
- Demo data

Trong giai đoạn phát triển chính thức, hệ thống dự kiến chuyển sang:

- Laravel
- RESTful API
- MySQL
- Flutter Mobile

---

# 3. KIẾN TRÚC HỆ THỐNG ĐỊNH HƯỚNG

Kiến trúc dự kiến:

```text
                    ┌─────────────────────┐
                    │      MySQL DB       │
                    └──────────▲──────────┘
                               │
                               │
                    ┌──────────┴──────────┐
                    │ Laravel Backend/API │
                    │    RESTful API      │
                    └───────▲───────▲─────┘
                            │       │
                     HTTP/JSON       │
                            │       │
                ┌───────────┘       └────────────┐
                │                                │
       ┌────────▼─────────┐             ┌────────▼─────────┐
       │ Website          │             │ Flutter Mobile   │
       │ Quản lý/Nhân viên│             │ Khách hàng       │
       └──────────────────┘             └──────────────────┘
````

## 3.1. Website

Website phục vụ chủ yếu:

* Quản lý
* Nhân viên

Các chức năng chính:

* Đăng nhập
* Dashboard
* Quản lý đơn hàng
* Tạo đơn hàng
* Quản lý khách hàng
* Quản lý dịch vụ
* Quản lý loại đồ giặt
* Quản lý bảng giá
* Quản lý khuyến mãi
* Quản lý giao nhận
* Thanh toán
* Hóa đơn
* Báo cáo
* Quản lý tài khoản/vai trò
* Thông báo

## 3.2. Mobile

Ứng dụng Flutter phục vụ chủ yếu:

* Khách hàng

Các chức năng chính:

* Đăng ký
* Đăng nhập
* Xem trang chủ
* Tạo yêu cầu giặt ủi
* Xem đơn hàng
* Theo dõi trạng thái đơn
* Xem khuyến mãi
* Sử dụng điểm tích lũy
* Nhận thông báo
* Đánh giá
* Quản lý tài khoản

## 3.3. Không kết nối Flutter trực tiếp với MySQL

Kiến trúc bắt buộc theo định hướng:

```text
Flutter
   ↓
HTTP/JSON
   ↓
Laravel REST API
   ↓
MySQL
```

Không thiết kế:

```text
Flutter → MySQL
```

---

# 4. SOURCE CODE HIỆN TẠI

Repository hiện tại là prototype giao diện quản lý cửa hàng giặt ủi.

## 4.1. Công nghệ hiện tại

* PHP thuần
* HTML
* CSS
* JavaScript
* JSON file
* Responsive UI

## 4.2. Cấu trúc chính hiện tại

```text
/
├── assets/
├── data/
├── includes/
│
├── accounts.php
├── actions.php
├── bookings.php
├── config.php
├── customers.php
├── dashboard.php
├── garment-types.php
├── index.php
├── login.php
├── logout.php
├── orders.php
├── payments.php
├── promotions.php
├── reports.php
├── services.php
│
└── TAI_LIEU_MODULE.md
```

## 4.3. Data hiện tại

Prototype hiện tại sử dụng JSON để lưu dữ liệu demo.

Ví dụ:

```text
data/store.json
data/seed.json
```

Cách này chỉ phục vụ prototype.

Không xem JSON hiện tại là database chính thức.

---

# 5. VAI TRÒ NGƯỜI DÙNG

Hệ thống có các vai trò chính:

## 5.1. Quản lý

Quản lý các hoạt động của cửa hàng.

Các nhóm chức năng:

* Quản lý dịch vụ và giá
* Quản lý chương trình khuyến mãi
* Theo dõi hoạt động kinh doanh
* Quản lý nhân viên/tài khoản
* Quản lý khách hàng
* Xem báo cáo
* Theo dõi đơn hàng
* Quản lý giao nhận
* Theo dõi thanh toán

Trong tài liệu nghiệp vụ, tên actor chuẩn là:

**Quản lý**

Không dùng `Admin` làm tên actor nghiệp vụ nếu không có yêu cầu khác.

## 5.2. Nhân viên

Thực hiện các nghiệp vụ tại cửa hàng:

* Tiếp nhận yêu cầu/đồ giặt
* Kiểm tra và phân loại đồ
* Chọn dịch vụ
* Ghi nhận thông tin đơn
* Cập nhật trạng thái xử lý
* Thực hiện các công việc liên quan đến đơn hàng
* Hỗ trợ giao nhận
* Thanh toán
* Có thể tạo đơn thay khách hàng

## 5.3. Khách hàng

Khách hàng có thể:

* Đăng ký/đăng nhập
* Tạo yêu cầu giặt ủi
* Xem đơn hàng
* Theo dõi trạng thái
* Sử dụng khuyến mãi
* Sử dụng điểm tích lũy
* Nhận thông báo
* Đánh giá dịch vụ

---

# 6. MÔ HÌNH NGHIỆP VỤ ĐƠN HÀNG

Đơn hàng là nghiệp vụ trung tâm của hệ thống.

Quy trình tổng quát:

```text
Khách hàng
    ↓
Tạo yêu cầu giặt ủi
    ↓
Nhân viên tiếp nhận
    ↓
Kiểm tra / phân loại đồ
    ↓
Chọn dịch vụ
    ↓
Ghi nhận chi tiết đơn
    ↓
Xác nhận đơn
    ↓
Xử lý / giặt ủi
    ↓
Cập nhật trạng thái
    ↓
Hoàn thành
    ↓
Thanh toán
    ↓
Trả đồ / giao đồ
    ↓
Khách hàng đánh giá
```

Lưu ý:

* `Tạo yêu cầu giặt ủi` và `Tiếp nhận yêu cầu giặt ủi` là hai bước nghiệp vụ khác nhau.
* Khách hàng tạo yêu cầu.
* Nhân viên tiếp nhận và kiểm tra đồ thực tế.
* Không gộp toàn bộ quá trình thành một thao tác duy nhất nếu đang thiết kế nghiệp vụ.

---

# 7. MÔ HÌNH ĐƠN HÀNG

## 7.1. Không giới hạn một dịch vụ trên một đơn

Một đơn hàng có thể chứa nhiều dòng chi tiết.

Quan hệ:

```text
DonHang 1 ───── 1..* ChiTietDonHang
```

Ví dụ:

```text
Đơn hàng #DH001

Chi tiết 1:
- Áo sơ mi
- Giặt thường
- 3 cái
- Đơn giá ...
- Thành tiền ...

Chi tiết 2:
- Chăn
- Giặt sấy
- 1 cái
- Đơn giá ...
- Thành tiền ...
```

## 7.2. Chi tiết đơn hàng

`ChiTietDonHang` dự kiến có:

```text
ChiTietDonHangID
DonHangID
DichVuID
LoaiDoGiatID
SoLuong
KhoiLuong
DonGia
ThanhTien
```

Có thể bổ sung thuộc tính khác nếu nghiệp vụ thực tế yêu cầu.

## 7.3. Người tạo đơn

Đơn hàng có thể được tạo bởi:

* Khách hàng trên Mobile
* Nhân viên tại cửa hàng

Không nhất thiết phải thêm thuộc tính `NguoiTaoDon` nếu hệ thống không có yêu cầu theo dõi người tạo.

`NhanVienID` trong `DonHang` có thể dùng để xác định nhân viên phụ trách/tiếp nhận đơn.

Không tự ý hiểu `NhanVienID` là người tạo đơn nếu chưa có yêu cầu.

---

# 8. DỊCH VỤ, LOẠI ĐỒ VÀ BẢNG GIÁ

## 8.1. Dịch vụ

`DichVu` mô tả loại dịch vụ mà cửa hàng cung cấp.

Ví dụ:

* Giặt thường
* Giặt sấy
* Giặt hấp
* Vệ sinh đặc biệt

`DichVu` không nên chứa `DonGia` cố định.

## 8.2. Loại đồ giặt

`LoaiDoGiat` mô tả loại đồ:

* Áo
* Quần
* Chăn
* Mền
* Rèm
* Thảm
* ...

Không đặt `base_price` cố định trong `LoaiDoGiat` nếu giá thực tế phụ thuộc vào cả dịch vụ và loại đồ.

## 8.3. Bảng giá

Giá được quản lý thông qua `BangGia`.

Quan hệ:

```text
DichVu 1 ───── 0..* BangGia
LoaiDoGiat 1 ───── 0..* BangGia
```

`BangGia` dự kiến:

```text
BangGiaID
DichVuID
LoaiDoGiatID
DonViTinh
DonGia
NgayApDung
TrangThai
```

Ví dụ:

```text
Giặt thường + Áo + kg → 25.000
Giặt hấp + Áo + cái → 30.000
Giặt sấy + Chăn + cái → 80.000
```

## 8.4. Giá trong ChiTietDonHang

`ChiTietDonHang` vẫn lưu:

```text
DonGia
```

Mục đích:

Lưu lại giá tại thời điểm khách hàng đặt/đơn được xác nhận.

Không lấy giá hiện tại từ `BangGia` để thay đổi ngược giá của các đơn hàng cũ.

---

# 9. ĐIỂM TÍCH LŨY

Hệ thống sử dụng mô hình điểm tích lũy tổng quát.

Không hard-code quy tắc khảo sát như:

```text
6 lần sử dụng = 1 lần miễn phí
```

Quy tắc trên chỉ là thông tin khảo sát, không phải quy tắc cố định của hệ thống.

## 9.1. DiemTichLuy

Quan hệ:

```text
KhachHang 1 ───── 1 DiemTichLuy
```

Thuộc tính đề xuất:

```text
DiemTichLuyID
KhachHangID
DiemHienTai
NgayCapNhat
```

## 9.2. Sử dụng điểm

Khách hàng có thể dùng điểm để giảm giá đơn hàng.

Có thể lưu trong `DonHang`:

```text
DiemSuDung
TienGiamDoDiem
```

Không cần tạo `SuDungDiem` riêng nếu nghiệp vụ chưa yêu cầu lịch sử giao dịch điểm chi tiết.

---

# 10. KHUYẾN MÃI

Một đơn hàng chỉ áp dụng tối đa:

**01 chương trình/mã khuyến mãi**

Không hỗ trợ cộng dồn nhiều mã khuyến mãi trên cùng một đơn nếu chưa có yêu cầu mới.

Quan hệ:

```text
KhuyenMai 1 ───── 0..* DonHang
```

Từ phía `DonHang`:

```text
DonHang 0..1 ───── 1 KhuyenMai
```

## 10.1. KhuyenMai

Thuộc tính đề xuất:

```text
KhuyenMaiID
MaKhuyenMai
TenKhuyenMai
LoaiKhuyenMai
GiaTriGiam
GiaTriDonToiThieu
MucGiamToiDa
SoLuongSuDung
DieuKienApDung
NgayBatDau
NgayKetThuc
TrangThai
```

Loại khuyến mãi có thể gồm:

* Phần trăm
* Số tiền cố định

---

# 11. HÓA ĐƠN VÀ THANH TOÁN

## 11.1. HoaDon

Quan hệ:

```text
DonHang 1 ───── 0..1 HoaDon
```

Thuộc tính đề xuất:

```text
HoaDonID
DonHangID
NgayLap
TongTien
GiamGia
PhiGiaoHang
ThanhTien
TrangThaiHoaDon
```

## 11.2. ThanhToan

Quan hệ:

```text
DonHang 1 ───── 0..* ThanhToan
```

Thuộc tính đề xuất:

```text
ThanhToanID
DonHangID
SoTien
PhuongThuc
ThoiGian
TrangThai
MaGiaoDich
```

Phương thức có thể gồm:

* Tiền mặt
* Chuyển khoản
* Ví điện tử

Không lưu `PhuongThucThanhToan` trực tiếp trong `DonHang` nếu đã quản lý bằng `ThanhToan`.

---

# 12. GIAO NHẬN

Giao nhận cần được thiết kế theo nghiệp vụ cuối cùng của nhóm.

Nếu hệ thống chỉ có một lần giao đồ/trả đồ:

```text
DonHang 1 ───── 0..1 GiaoHang
```

Nếu hệ thống hỗ trợ nhiều lần giao nhận, ví dụ:

* Nhận đồ
* Trả đồ

thì có thể dùng:

```text
DonHang 1 ───── 0..* GiaoNhan
```

Không tự ý chọn mô hình nhiều lần giao nhận nếu phạm vi nghiệp vụ cuối cùng chưa xác nhận.

---

# 13. THÔNG BÁO

`ThongBao` được tách khỏi `TinNhan`.

`ThongBao` dùng để gửi các thông tin hệ thống như:

* Đơn hàng đã tiếp nhận
* Đơn hàng đang xử lý
* Đơn hàng hoàn thành
* Đơn hàng đã giao
* Khuyến mãi
* Thông tin khác

Thuộc tính đề xuất:

```text
ThongBaoID
TaiKhoanID
LoaiThongBao
NoiDung
ThoiGianGui
DaDoc
DonHangID
```

---

# 14. TIN NHẮN

`TinNhan` đại diện cho chức năng chat/hỗ trợ nếu hệ thống có triển khai.

Không đồng nhất:

```text
TinNhan
```

với:

```text
ThongBao
```

Nếu chức năng chat không nằm trong phạm vi cuối cùng thì có thể loại khỏi phiên bản chính thức.

---

# 15. ĐÁNH GIÁ

`DanhGia` dùng để khách hàng đánh giá dịch vụ sau khi hoàn thành đơn.

Có thể gồm:

```text
DanhGiaID
KhachHangID
DonHangID
SoSao
NoiDung
NgayDanhGia
TrangThai
```

Quan hệ dự kiến:

```text
KhachHang 1 ───── 0..* DanhGia
DonHang 1 ───── 0..1 DanhGia
```

---

# 16. TÀI KHOẢN VÀ PHÂN QUYỀN

Tài khoản dùng để xác thực người dùng.

Các vai trò chính:

```text
Quản lý
Nhân viên
Khách hàng
```

Mô hình chính thức có thể sử dụng:

```text
TaiKhoan
VaiTro
NhanVien
KhachHang
```

Không nhất thiết phải dùng trực tiếp:

```text
admin
staff
customer
```

làm mô hình nghiệp vụ cuối cùng.

Đây chỉ là cách đặt role hiện tại trong prototype.

---

# 17. BÁO CÁO

`BaoCao` chủ yếu được xem là dữ liệu đầu ra từ hệ thống.

Không mặc định tạo bảng `BaoCao` trong database nếu không có yêu cầu lưu báo cáo.

Ví dụ báo cáo:

* Doanh thu
* Số lượng đơn
* Tình trạng đơn
* Dịch vụ được sử dụng
* Khách hàng
* Hiệu quả kinh doanh

---

# 18. CÁC CLASS QUAN TRỌNG

Mô hình phân tích hiện tại dự kiến có các class/domain chính:

```text
TaiKhoan
VaiTro
NhanVien
KhachHang

DonHang
ChiTietDonHang

DichVu
LoaiDoGiat
BangGia

DiemTichLuy
KhuyenMai

HoaDon
ThanhToan

GiaoHang / GiaoNhan

ThongBao
TinNhan
DanhGia
```

Không nhất thiết tất cả class đều phải xuất hiện trên một sơ đồ lớp duy nhất.

Có thể chia sơ đồ để dễ đọc.

---

# 19. QUAN HỆ LỚP QUAN TRỌNG

Các quan hệ đã thống nhất:

```text
DonHang 1 ───── 1..* ChiTietDonHang

DichVu 1 ───── 0..* ChiTietDonHang

LoaiDoGiat 1 ───── 0..* ChiTietDonHang

DichVu 1 ───── 0..* BangGia

LoaiDoGiat 1 ───── 0..* BangGia

KhachHang 1 ───── 1 DiemTichLuy

KhuyenMai 1 ───── 0..* DonHang

DonHang 1 ───── 0..1 HoaDon

DonHang 1 ───── 0..* ThanhToan

KhachHang 1 ───── 0..* DanhGia

DonHang 1 ───── 0..1 DanhGia
```

Không vẽ trực tiếp:

```text
DonHang ───── DichVu
```

nếu `ChiTietDonHang` đã đóng vai trò trung gian.

---

# 20. AUDIT DATA

Các thông tin audit là:

**Thuộc tính dữ liệu**, không phải operation.

Ví dụ:

```text
NguoiTaoID
NguoiCapNhatID
NgayTao
NgayCapNhat
IsDelete
NgayXoa
```

Trong sơ đồ lớp phân tích không nhất thiết phải đưa toàn bộ audit field vào vì có thể làm sơ đồ quá lớn.

Các trường audit đầy đủ nên được xem xét khi thiết kế database.

---

# 21. OPERATION TRONG SƠ ĐỒ LỚP

Đối với:

**Analysis Class Diagram**

Không cần cố nhét toàn bộ CRUD operation vào class.

Ví dụ không cần đưa:

```text
Them()
Sua()
Xoa()
TimKiem()
```

vào mọi entity chỉ để làm cho class diagram đầy đủ.

Operation phù hợp hơn với:

* Sequence Diagram
* Design Class Diagram
* Controller/Service
* API

Khi chuyển sang Design Class Diagram mới xem xét operation cụ thể.

---

# 22. NGUYÊN TẮC THIẾT KẾ UI

UI prototype hiện tại có thể sử dụng để:

* Tham khảo bố cục
* Tham khảo luồng thao tác
* Tham khảo component
* Tham khảo cách hiển thị dữ liệu

Nhưng UI hiện tại không phải yêu cầu cuối cùng.

Khi thiết kế UI mới, phải đối chiếu với:

1. Nghiệp vụ đã chốt.
2. Use Case.
3. Class Diagram.
4. Database.
5. API.

Không sửa nghiệp vụ chỉ để phù hợp với UI prototype hiện tại.

---

# 23. MODULE HIỆN CÓ TRONG PROTOTYPE

## 23.1. Login

File:

```text
login.php
logout.php
```

Chức năng:

* Đăng nhập
* Đăng xuất
* Session

---

## 23.2. Dashboard

File:

```text
dashboard.php
```

Hiển thị tổng quan:

* Số đơn
* Doanh thu
* Đơn gần đây
* Tiến độ xử lý đơn
* Khuyến mãi
* Một số thống kê

Dashboard có thể được thiết kế lại khi API/database chính thức hoàn thành.

---

## 23.3. Accounts

File:

```text
accounts.php
```

Prototype hiện hỗ trợ:

* Danh sách tài khoản
* Thêm
* Sửa
* Xóa
* Khóa/mở khóa
* Đổi mật khẩu
* Role

Khi chuyển sang hệ thống chính thức cần đồng bộ với:

```text
TaiKhoan
VaiTro
NhanVien
KhachHang
```

---

## 23.4. Customers

File:

```text
customers.php
```

Prototype hỗ trợ:

* Danh sách khách hàng
* Tìm kiếm
* Thêm
* Sửa
* Xóa
* Xem lịch sử đơn
* Thông tin khách hàng

---

## 23.5. Services

File:

```text
services.php
```

Prototype hiện tại đang lưu giá trực tiếp trong service.

Đây là điểm cần thay đổi khi triển khai chính thức.

Định hướng:

```text
DichVu
    +
LoaiDoGiat
    ↓
BangGia
```

Không để:

```text
DichVu.DonGia
```

làm giá duy nhất của hệ thống nếu giá phụ thuộc loại đồ.

---

## 23.6. Garment Types

File:

```text
garment-types.php
```

Prototype hiện có `base_price`.

Khi triển khai chính thức:

* Không dùng `base_price` làm giá chính.
* Giá chuyển sang `BangGia`.

---

## 23.7. Orders

File:

```text
orders.php
```

Đây là module cần nâng cấp đáng kể.

Prototype hiện tại đang có xu hướng:

```text
1 Order
    └── 1 Service
    └── 1 Garment Type
```

Mô hình chính thức:

```text
DonHang
    ├── ChiTietDonHang
    │      ├── DichVu
    │      ├── LoaiDoGiat
    │      ├── SoLuong
    │      ├── KhoiLuong
    │      ├── DonGia
    │      └── ThanhTien
    │
    ├── KhuyenMai
    ├── DiemTichLuy
    ├── HoaDon
    ├── ThanhToan
    └── GiaoHang/GiaoNhan
```

Đây là một trong những module quan trọng nhất khi migrate sang Laravel/MySQL.

---

## 23.8. Bookings

File:

```text
bookings.php
```

Prototype hiện có chức năng đặt lịch/giao nhận.

Không mặc định xem `Booking` là entity chính thức.

Cần đối chiếu với phạm vi nghiệp vụ cuối cùng.

Nếu nghiệp vụ chỉ cần giao/trả đồ thì ưu tiên mô hình:

```text
GiaoHang
```

hoặc:

```text
GiaoNhan
```

thay vì giữ nguyên `Booking` chỉ vì prototype có module này.

---

## 23.9. Payments

File:

```text
payments.php
```

Prototype đã có cấu trúc gần với:

```text
ThanhToan
```

Các thông tin:

```text
order_id
method
amount
paid_at
status
transaction_note
```

Khi triển khai chính thức, chuyển sang model/database `ThanhToan`.

---

## 23.10. Promotions

File:

```text
promotions.php
```

Prototype hỗ trợ:

* Mã khuyến mãi
* Tên
* Loại
* Giá trị
* Đơn tối thiểu
* Thời gian bắt đầu/kết thúc
* Trạng thái

Định hướng chính thức:

```text
KhuyenMai
    ↓
DonHang
```

Một đơn chỉ áp dụng tối đa 1 khuyến mãi.

---

## 23.11. Reports

File:

```text
reports.php
```

Dùng để hiển thị dữ liệu thống kê/báo cáo.

Không mặc định tạo bảng `BaoCao`.

---

# 24. CÁC MODULE CẦN BỔ SUNG SO VỚI PROTOTYPE

Prototype hiện tại còn thiếu hoặc cần nâng cấp:

## 24.1. Bảng giá

Cần có UI/API/database cho:

```text
BangGia
```

và cho phép quản lý giá theo:

```text
Dịch vụ
+
Loại đồ giặt
```

## 24.2. Chi tiết đơn hàng

Cần nâng cấp `orders.php` để một đơn có nhiều:

```text
ChiTietDonHang
```

## 24.3. Điểm tích lũy

Cần bổ sung:

```text
DiemTichLuy
```

và khả năng:

```text
DiemSuDung
TienGiamDoDiem
```

trong đơn hàng nếu triển khai.

## 24.4. Hóa đơn

Nếu phạm vi cuối cùng giữ `HoaDon`, cần bổ sung UI và API.

## 24.5. Thông báo

Cần bổ sung module:

```text
ThongBao
```

## 24.6. Đánh giá

Cần có chức năng khách hàng đánh giá đơn/dịch vụ sau khi hoàn thành.

---

# 25. TRẠNG THÁI ĐƠN HÀNG

Prototype hiện tại có các trạng thái dạng:

```text
pending
confirmed
processing
completed
cancelled
```

Khi thiết kế chính thức, cần chuẩn hóa tên trạng thái theo nghiệp vụ và UI.

Không tự ý thêm hàng chục trạng thái nếu không cần thiết.

Một trạng thái mới phải có ý nghĩa nghiệp vụ rõ ràng.

---

# 26. API ĐỊNH HƯỚNG

Backend chính thức dự kiến sử dụng REST API.

Ví dụ:

## Authentication

```http
POST /api/auth/login
POST /api/auth/register
POST /api/auth/logout
PUT  /api/auth/change-password
```

## Customer

```http
GET /api/customers/{id}
PUT /api/customers/{id}
GET /api/customers/{id}/orders
```

## Services

```http
GET    /api/services
GET    /api/services/{id}
POST   /api/services
PUT    /api/services/{id}
DELETE /api/services/{id}
```

## Orders

```http
POST /api/orders
GET  /api/orders/{id}
GET  /api/customers/{id}/orders
PUT  /api/orders/{id}/status
```

## Promotions

```http
GET    /api/promotions
POST   /api/promotions
PUT    /api/promotions/{id}
DELETE /api/promotions/{id}
```

## Delivery

```http
POST /api/deliveries
GET  /api/deliveries/{id}
PUT  /api/deliveries/{id}/status
```

Đây chỉ là API định hướng.

Không tự ý xem đây là danh sách endpoint cuối cùng nếu nhóm chưa chốt API.

---

# 27. NGUYÊN TẮC KHI AI ĐƯỢC YÊU CẦU SỬA CODE

Khi AI được yêu cầu sửa code trong repository:

## Bước 1

Đọc:

```text
TAI_LIEU_MODULE.md
```

## Bước 2

Xác định:

* Module đang sửa
* Nghiệp vụ liên quan
* Entity liên quan
* Quan hệ dữ liệu
* Vai trò sử dụng

## Bước 3

Kiểm tra source code hiện tại.

## Bước 4

Phân biệt:

```text
Prototype hiện tại
```

và:

```text
Thiết kế chính thức
```

## Bước 5

Chỉ sửa các file cần thiết.

Không tự ý refactor toàn bộ project nếu người dùng không yêu cầu.

## Bước 6

Sau khi sửa phải mô tả:

* Đã sửa file nào
* Đã sửa gì
* Vì sao sửa
* Có ảnh hưởng module khác không
* Có phần nào chưa làm không

---

# 28. NGUYÊN TẮC KHI THIẾT KẾ DATABASE

Không thiết kế database chỉ dựa trên source code prototype.

Phải ưu tiên:

```text
Nghiệp vụ
   ↓
Use Case
   ↓
Analysis Class Diagram
   ↓
Design Class Diagram
   ↓
ERD / Data Model
   ↓
Database
```

Source code prototype chỉ là tài liệu tham khảo.

---

# 29. NGUYÊN TẮC KHI THIẾT KẾ USE CASE

Business Use Case phải tập trung vào:

**Quy trình nghiệp vụ**

Không đưa các chức năng kỹ thuật như:

* Đăng ký tài khoản
* Đăng nhập
* Quản lý quyền
* Gửi thông báo
* Báo cáo

vào Business Use Case nếu đó không phải nghiệp vụ cốt lõi.

Các chức năng hệ thống có thể được thể hiện ở System Use Case.

---

# 30. NGUYÊN TẮC KHI VẼ ACTIVITY DIAGRAM

Business Activity Diagram:

* Tập trung vào nghiệp vụ.
* Không đưa UI.
* Không đưa Controller.
* Không đưa Database.
* Không đưa Entity.
* Không đưa API.
* Không đưa Laravel.
* Không đưa MySQL.

Ví dụ:

```text
Start
 ↓
Khách hàng tạo yêu cầu
 ↓
Nhân viên tiếp nhận
 ↓
Kiểm tra đồ
 ↓
Chọn dịch vụ
 ↓
...
 ↓
End
```

---

# 31. NGUYÊN TẮC KHI VẼ SEQUENCE DIAGRAM

Business Sequence Diagram:

* Tập trung vào tương tác giữa Actor và nghiệp vụ.
* Không đưa UI/Boundary/Controller/Database nếu đang mô tả business sequence.

System Sequence / Design Sequence mới có thể sử dụng:

```text
Actor
UI
Controller
Service
Repository
Database
```

Không trộn hai mức này vào cùng một sơ đồ nếu không có mục đích rõ ràng.

---

# 32. THỨ TỰ PHÁT TRIỂN DỰ KIẾN

Thứ tự phát triển:

```text
1. Hoàn thiện nghiệp vụ
        ↓
2. Use Case
        ↓
3. Activity Diagram
        ↓
4. Sequence Diagram
        ↓
5. Analysis Class Diagram
        ↓
6. Design Class Diagram
        ↓
7. Data Model / ERD
        ↓
8. Database
        ↓
9. API
        ↓
10. Website
        ↓
11. Flutter Mobile
        ↓
12. Integration / Testing
```

UI prototype hiện tại có thể được sử dụng song song ở giai đoạn thiết kế giao diện.

---

# 33. CÁC ĐIỂM KHÔNG ĐƯỢC TỰ Ý THAY ĐỔI

AI không được tự ý:

1. Đổi tên đề tài.
2. Đổi actor nghiệp vụ `Quản lý`.
3. Đưa nghiệp vụ HOTELNT vào dự án.
4. Cho Flutter kết nối trực tiếp MySQL.
5. Đặt giá cố định vào `DichVu` nếu giá phụ thuộc loại đồ.
6. Đặt `base_price` làm giá chính của `LoaiDoGiat`.
7. Cho một đơn áp dụng nhiều khuyến mãi nếu chưa có yêu cầu mới.
8. Tạo `DonHangKhuyenMai` khi mô hình chỉ cho phép một khuyến mãi/đơn.
9. Bỏ `ChiTietDonHang` để quay lại mô hình một dịch vụ/đơn.
10. Biến quy tắc khảo sát “6 lần = 1 lần miễn phí” thành business rule cố định.
11. Kết luận một nghiệp vụ `TBD` mà chưa được nhóm/giảng viên chốt.
12. Xem prototype hiện tại là database cuối cùng.

---

# 34. CÁCH PROMPT AI KHI LÀM VIỆC VỚI PROJECT

Có thể sử dụng các prompt mẫu sau.

## 34.1. Phân tích một module

```text
Hãy đọc TAI_LIEU_MODULE.md trước.

Tôi muốn phân tích module Orders.

Hãy:
1. Mô tả module hiện tại.
2. So sánh với nghiệp vụ chính thức.
3. Chỉ ra điểm đúng.
4. Chỉ ra điểm cần sửa.
5. Chỉ ra entity/database liên quan.
6. Đề xuất cấu trúc mới.

Không tự ý thay đổi nghiệp vụ chưa được chốt.
```

## 34.2. Sửa code

```text
Hãy đọc TAI_LIEU_MODULE.md trước.

Tôi muốn sửa module Orders.

Mục tiêu:
- Một DonHang có nhiều ChiTietDonHang.
- ChiTietDonHang liên kết DichVu và LoaiDoGiat.
- Lưu DonGia tại thời điểm tạo đơn.

Hãy:
1. Kiểm tra code hiện tại.
2. Chỉ ra các file cần sửa.
3. Đề xuất cách sửa.
4. Sau đó mới viết code.

Không tự ý sửa các module khác nếu không cần thiết.
```

## 34.3. Review UI

```text
Hãy đọc TAI_LIEU_MODULE.md trước.

Hãy review giao diện module Orders.

Kiểm tra:
- Có phù hợp nghiệp vụ không?
- Có hỗ trợ nhiều ChiTietDonHang không?
- Có khuyến mãi không?
- Có điểm tích lũy không?
- Có thanh toán không?
- Có phù hợp với vai trò Quản lý/Nhân viên không?

Phân loại:
- GIỮ
- SỬA
- THÊM
- XÓA

Không tự ý thêm nghiệp vụ ngoài tài liệu.
```

## 34.4. Migrate PHP sang Laravel

```text
Hãy đọc TAI_LIEU_MODULE.md trước.

Tôi muốn migrate module [TÊN MODULE] từ PHP prototype sang Laravel.

Hãy:
1. Phân tích code PHP hiện tại.
2. Xác định Model.
3. Xác định Migration.
4. Xác định Controller.
5. Xác định Route.
6. Xác định Blade View.
7. Xác định API nếu cần.
8. Chỉ ra phần nào có thể tái sử dụng.
9. Viết code theo từng bước.

Không kết nối Flutter trực tiếp với MySQL.
```

---

# 35. QUY TẮC ƯU TIÊN THÔNG TIN

Khi có mâu thuẫn giữa các nguồn, ưu tiên:

```text
1. Yêu cầu/nhận xét mới nhất của giảng viên
        ↓
2. Quyết định mới nhất của nhóm
        ↓
3. Tài liệu nghiệp vụ / Use Case / Class Diagram mới nhất
        ↓
4. TAI_LIEU_MODULE.md
        ↓
5. Source code prototype
```

Source code prototype không được tự động ghi đè yêu cầu nghiệp vụ.

---

# 36. TRẠNG THÁI CỦA TÀI LIỆU

Các nội dung trong file này được chia thành:

## Đã thống nhất

Là các quyết định đã được nhóm xác định rõ.

AI có thể sử dụng trực tiếp.

## Định hướng

Là kiến trúc/thiết kế đang được đề xuất.

AI không được coi là yêu cầu bắt buộc nếu nhóm chưa chốt.

## TBD

Là vấn đề chưa quyết định.

AI phải giữ nguyên `TBD` và không tự ý kết luận.

---

# 37. GHI CHÚ QUAN TRỌNG CHO AI

Khi trả lời các câu hỏi liên quan đến project:

* Ưu tiên tiếng Việt.
* Giải thích theo hướng dễ hiểu cho sinh viên năm 4.
* Không sử dụng thuật ngữ quá chuyên sâu mà không giải thích.
* Nếu đề xuất thay đổi kiến trúc, phải nói rõ lý do.
* Nếu có nhiều phương án, phải chỉ ra ưu/nhược điểm.
* Không tự ý thay đổi nghiệp vụ đã thống nhất.
* Không tự ý thêm chức năng chỉ vì “hệ thống thông thường nên có”.
* Phân biệt rõ:

  * nghiệp vụ
  * UI
  * API
  * database
  * implementation
* Khi review code, luôn đối chiếu với mô hình nghiệp vụ.
* Khi review UI, không dùng UI prototype làm nguồn duy nhất để quyết định nghiệp vụ.
* Khi chưa đủ thông tin, đánh dấu `TBD` thay vì đoán.

---

# 38. TÓM TẮT HỆ THỐNG

```text
ĐỀ TÀI
Xây dựng ứng dụng quản lí cửa hàng giặt ủi

ACTOR
- Quản lý
- Nhân viên
- Khách hàng

CLIENT
- Website
- Flutter Mobile

BACKEND
- Laravel REST API

DATABASE
- MySQL

NGHIỆP VỤ TRỌNG TÂM
- Khách hàng tạo yêu cầu giặt ủi
- Nhân viên tiếp nhận
- Quản lý đơn hàng
- Xử lý giặt ủi
- Giao/trả đồ
- Thanh toán
- Hóa đơn
- Khuyến mãi
- Điểm tích lũy
- Đánh giá
- Báo cáo

ENTITY TRỌNG TÂM
- TaiKhoan
- VaiTro
- NhanVien
- KhachHang
- DonHang
- ChiTietDonHang
- DichVu
- LoaiDoGiat
- BangGia
- DiemTichLuy
- KhuyenMai
- HoaDon
- ThanhToan
- GiaoHang/GiaoNhan
- ThongBao
- DanhGia

NGUYÊN TẮC QUAN TRỌNG
- Không kết nối Flutter trực tiếp MySQL.
- Một DonHang có nhiều ChiTietDonHang.
- Giá nằm ở BangGia khi phụ thuộc DichVu + LoaiDoGiat.
- ChiTietDonHang lưu DonGia tại thời điểm đặt/xác nhận.
- Một DonHang tối đa một KhuyenMai.
- Điểm tích lũy dùng mô hình tổng quát.
- Không hard-code quy tắc khảo sát thành business rule.
- Prototype PHP/JSON chỉ là nền tảng UI/demo.
```

