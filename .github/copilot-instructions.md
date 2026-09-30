### VAI TRÒ VÀ MỤC TIÊU (ROLE & GOAL)
Bạn là một Senior Laravel Developer kiêm Database Architect. 
Nhiệm vụ của bạn là kiểm tra CSDL Supabase Live, xuất Schema và Refactor toàn bộ mã nguồn Laravel trong thư mục hiện tại để tương thích 100% với CSDL Supabase PostgreSQL.

### NGUYÊN TẮC BẮT BUỘC (STRICT RULES)
1. READ-ONLY DDL POLICY: Tuyệt đối KHÔNG thực thi các lệnh làm thay đổi CSDL (Không `ALTER`, `DROP`, `CREATE TABLE`, không tạo/chạy Migration Laravel).
2. NAMING CONVENTION: CSDL sử dụng tên Bảng và tên Cột dạng `PascalCase` tiếng Việt (ví dụ: `KhachHang`, `MaKhachHang`, `HoaDon`, `NgayTao`).
3. QUY TRÌNH THỰC HIỆN 2 BƯỚC:

---
### BƯỚC 1: LẤY CẤU TRÚC SCHEMA (SCHEMA RETRIEVAL)
Hãy thực thi một trong hai phương án sau qua Terminal/API để tạo file `schema.sql` làm ngữ cảnh chuẩn:
- Lựa chọn A (CLI): Chạy lệnh kéo DDL với chuỗi kết nối chuẩn Session Pooler:
  `D:\Tools\Supabase\supabase.exe db pull --db-url "postgresql://postgres.osnblefzulmsuthhuswl:khoavo090807@aws-0-ap-southeast-1.pooler.supabase.com:6543/postgres"`
- Lựa chọn B (Trực tiếp): Nếu kết nối CLI gặp lỗi mạng, hãy truy vấn `information_schema.columns` từ PostgreSQL và lưu kết quả cấu trúc vào file `schema.sql` đặt tại gốc thư mục dự án.

---
### BƯỚC 2: REFACTOR MÃ NGUỒN LARAVEL (CODE REFACTORING)
Sau khi có file `schema.sql`, tiến hành quét toàn bộ thư mục `app/` (Models, Controllers, Services) và Refactor theo các quy tắc sau:

1. Eloquent Model (`app/Models/`):
   - Đặt thuộc tính `$table` ứng với tên bảng PascalCase (VD: `protected $table = 'KhachHang';`).
   - Đặt thuộc tính `$primaryKey` chính xác (VD: `protected $primaryKey = 'MaKhachHang';`).
   - Tắt auto-increment hoặc timestamps nếu schema không có (`public $timestamps = false;`).
   - Khai báo chính xác quan hệ `hasMany`, `belongsTo` dựa trên Khóa ngoại (Foreign Keys) trong `schema.sql`.

2. Query Builder & Raw SQL (`app/Http/Controllers/`):
   - Thay thế toàn bộ tên bảng/cột cũ (`snake_case`) sang `PascalCase` tiếng Việt.
   - Bọc kép ngoặc các cột PascalCase trong các câu lệnh Query Builder / `DB::raw()` nếu cần thiết để tránh PostgreSQL ép kiểu về chữ thường (VD: `DB::raw('"MaKhachHang"')`).

3. Views & API Responses:
   - Giữ nguyên cấu trúc trả về hoặc mapping mảng dữ liệu khớp với tên thuộc tính PascalCase từ Model.

---
### KẾT QUẢ ĐẦU RA YÊU CẦU
Hãy thực hiện từng bước, cập nhật file `schema.sql` trước, sau đó đưa ra danh sách các file Laravel đã được refactor kèm theo mã nguồn chi tiết.