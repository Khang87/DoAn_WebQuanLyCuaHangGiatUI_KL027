# Đối chiếu snapshot với Live Supabase — 07/10/2026

Dự án QLGiatUi (`osnblefzulmsuthhuswl`), PostgreSQL 17, kết nối **Supabase** đã được chủ dự án chọn. Rà soát bằng `information_schema.columns`, `pg_attribute`, `pg_proc`, `pg_trigger`; không đọc dữ liệu khách hàng để suy luận cột có dùng hay không.

- Các bảng nghiệp vụ có sẵn trong `schema.sql`: không tìm thấy cột snapshot nào đã biến mất trên Live. Vì vậy **không có cột nào được xóa** chỉ để rút gọn tài liệu.
- Snapshot Booking thiếu bốn cột đã tồn tại: `PickupDistanceMeters`, `PickupDeliveryFee`, `DeliveryDistanceMeters`, `DeliveryFee`. Bổ sung vào tài liệu, không thực hiện ALTER trên Live.
- `HinhThucGiaoDo` và `DiaChiGiao` vẫn tồn tại và được `submit_laundry_booking_with_delivery` ghi. RPC cũng đồng bộ sang `HinhThucTraDo` và `DiaChiTra`. Giữ cả cột tương thích và cột chuẩn.
- `ChiTietBooking` không có `TinhTrangTruocKhiGiat`; chỉ `ChiTietDonHang` có. Đây là lý do không thể coi chi tiết đặt lịch là kiểm kê.
- `ThanhToan` không có `IdempotencyKey`; RPC thanh toán được sửa dùng `MaGiaoDich` hiện hữu.
- Snapshot là tài liệu tham chiếu nghiệp vụ, không phải script provisioning đầy đủ: Live còn có bảng quote phí giao nhận và các compatibility view viết thường. Không lấy việc bảng/view không nằm trong phạm vi snapshot làm lý do DROP.
- 13 định nghĩa hàm đã được apply và đọc lại từ catalog vào `schema.sql`. Fingerprint trước/sau giống nhau cho relation, column, trigger, constraint và metadata function (OID, chữ ký, kiểu trả về, owner, SECURITY DEFINER và ACL); chỉ nội dung hàm thay đổi, cùng việc cố định search_path cho hàm status. Không tạo migration, bảng/cột hoặc trigger mới trên Live.

## Thuật ngữ

| Ý nghĩa | Cột | Giá trị / nhãn UI |
|---|---|---|
| Loại giao nhận: làm việc gì | GiaoNhan.LoaiGiaoNhan | NHAN_DO → Nhận đồ; GIAO_DO → Giao đồ |
| Hình thức: làm ở đâu | GiaoNhan.HinhThuc | Tại cửa hàng; Tại nhà |
| Hình thức nhận đồ của booking | Booking.HinhThucNhanDo | Tại cửa hàng; Tại nhà |
| Hình thức trả đồ của booking | Booking.HinhThucTraDo | Tại cửa hàng; Tại nhà |
| Nhân viên phụ trách | Booking.NhanVienID | Người được giao xử lý |
| Người xác nhận | Booking.NhanVienXacNhanID | Tài khoản thao tác kiểm kê/xác nhận |

Form, trang chi tiết và danh sách giao nhận phải đọc đúng cột. Hai chặng nhận/trả của booking độc lập; chỉ chặng tại nhà tạo phiếu giao nhận. Thời gian hẹn nhận không tự dùng làm lịch giao trả.
