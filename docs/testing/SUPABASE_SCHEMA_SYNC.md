# Đồng bộ schema Supabase — 10/10/2026

Catalog đọc trực tiếp từ project `osnblefzulmsuthhuswl`, PostgreSQL 17.6, không
xuất hàng dữ liệu và không thực thi DDL trên production. Bản gốc chứa đủ 31 bảng
public / 253 cột nhưng thiếu nhiều đối tượng để khôi phục độc lập.

| Phạm vi | Kết quả |
|---|---|
| Metadata mọi schema không phải hệ thống PostgreSQL | 100 table/view entries; cột, FK, indexes, RLS flags, policies |
| Snapshot ứng dụng public/private | 32 bảng, 18 views, 26 sequences, 155 constraints, 76 indexes |
| Hành vi database ứng dụng | 54 routines, 12 triggers, comments và cấu hình SECURITY DEFINER/search_path |
| Phân quyền | 37 policies public + 3 policies Storage; ACL hiệu lực, 8 quyền UPDATE theo cột, default privileges, RLS flags |
| Supabase Realtime | Thành viên publication public.ThongBao và public.TinNhan; trigger Broadcast và registry private |
| Khôi phục PostgreSQL 17 tạm | Đạt; đối chiếu lại toàn bộ đối tượng ứng dụng với catalog nguồn |
| Production | Chỉ đọc cấu trúc; không sửa bảng, quyền, rows hoặc migration history |

`schema.sql` là snapshot cho project Supabase mới đã có các schema/role/extension
do nền tảng quản lý. Nó không phải migration nâng cấp database đã có dữ liệu.
Auth/Storage/Realtime internal tables được ghi trong catalog để đối chiếu, không
tái tạo bằng migrations Laravel. Theo hướng dẫn Supabase, tùy chỉnh của ứng dụng
trên Storage được xuất cùng snapshot; không có trigger Auth tùy chỉnh trong lượt
capture này. File platform stubs trong tests chỉ phục vụ container PostgreSQL tạm.

## Đối chiếu module

Rà soát 32 model, gồm các alias kế thừa. Không có cột `$fillable` không tồn tại và
không có cột nghiệp vụ mới so với bảng/cột trong snapshot cũ. Eloquent hydrate
các cột từ database kể cả khi chúng không nằm trong `$fillable`; `$fillable` là
quyền ghi qua mass assignment, không phải danh mục cột.

| Model/module | Quyết định |
|---|---|
| KhachHang / payments Blade | Thêm đường đọc avatar theo RPC mobile: AvatarUrl → TaiKhoan.AvatarURL → ảnh mặc định; giữ escaping và onerror fallback |
| ThongBao | Cast TinNhanID/BookingID thành integer, thêm belongsTo tinNhan/booking; FK do trigger tạo, không mở input/fillable |
| Booking | Voucher, dấu đã trừ, phí/khoảng cách quote, phương thức thanh toán và cột giao đồ legacy thuộc RPC/server; giữ allowlist web. OrderService đã đọc tiền Booking và áp dụng voucher khi chuyển đơn |
| User / TaiKhoan | Avatar tài khoản/upload web vẫn giữ hợp đồng hiện có; không thay đổi ưu tiên ảnh profile của nhân viên |
| ChiTietBooking, DanhMucLoaiDoGiat, KhachHangDiaChi, LoaiDoGiat, NhatKyHeThong | Identity/khóa chính/thời gian tự sinh không cần form nhập |
| BangGia, ChiTietDonHang, DanhGia, DichVu, DiemTichLuy, DonHang, DonViTinh, GiaoNhan, HoaDon | Cột và quan hệ hiện tại khớp; không thay đổi phép tính tiền/quyền/điểm |
| KhuyenMai, LichSuThayDoiHoaDon, LoaiDichVu, NhanVien, Quyen, TaiKhoanVaiTro, ThanhToan, TinNhan, VaiTro, VaiTroQuyen | Khớp catalog, không cần mở thêm input |
| Coupon, Garment, ServiceCategory | Alias kế thừa model hiện hữu; không tạo bảng riêng |
| sessions, delivery_fee_quotes, delivery_fee_quote_attempts, private.web_message_channels | Hạ tầng session/quote/Realtime; dùng đúng lớp hiện tại, không thêm CRUD công khai |

Controllers/Requests/forms và API Resources đã đối chiếu theo các đường ghi hiện
có. Không phát sinh cột đầu vào mới cần mở API/form trong lượt này. Trường AvatarUrl
do RPC mobile cập nhật; thay đổi web chỉ bổ sung consumer đọc dữ liệu đã lưu.

## Tái tạo và kiểm tra

```bash
python3 scripts/schema/render.py --check
bash scripts/test-schema.sh
php vendor/bin/phpunit --filter SchemaModuleCompatibilityTest
```

Capture lại `database/schema/catalog-query.sql` và `grants-query.sql` qua kết nối
PostgreSQL chỉ đọc hoặc Supabase SQL tool; lưu trường `catalog` vào
`supabase-catalog.json`, trường `grants` vào `grants.json`, rồi chạy
`python3 scripts/schema/render.py`. Sequence bigint được xuất dạng text để tránh
mất chính xác khi đi qua JSON/JavaScript. Xem lại diff trước khi commit.

CI có bước restore/capture/compare; kiểm tra cả cột/type/default/identity, FK,
index, views/options, function body/ACL, trigger, policy, grants và publication.
Restore thử trên database có default grants rộng cho anon/authenticated; snapshot
reset quyền kế thừa trước khi tái cấp đúng quyền nguồn. Kiểm thử riêng xác nhận
client không đọc được sessions/registry private và chỉ sửa được các cột profile,
trạng thái đã đọc được phép. Global default ACL nguồn không có tùy chỉnh.
So sánh ACL mặc định ngầm và biểu thức literal-array varchar→text được chuẩn hóa
chỉ để xử lý cách PostgreSQL deparse lại cùng biểu thức sau restore.

Các kiểm thử module xác minh avatar mobile được ưu tiên, ảnh tài khoản/ảnh mặc
định được dùng khi thiếu, URL được escape trong Blade và FK thông báo không mở
mass assignment. Không cần sửa migrations lịch sử hoặc tạo migration production
cho cấu trúc đã có trên Supabase.

Kết quả local: **425 PHP tests / 2.229 assertions đạt**; restore/parity và các
hợp đồng ACL đạt. Thử bỏ tạm đường đọc AvatarUrl làm **2/4 kiểm thử module thất
bại**, xác nhận hồi quy này được phát hiện; mã nguồn đã được khôi phục.

Nguồn: https://supabase.com/docs/guides/platform/migrating-within-supabase/backup-restore
và https://laravel.com/docs/13.x/eloquent#mass-assignment.
