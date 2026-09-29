/* =========================================================
   DATABASE: QLGiatUi
   ĐỒ ÁN: XÂY DỰNG ỨNG DỤNG QUẢN LÝ CỬA HÀNG GIẶT ỦI
   DBMS: SQL SERVER

   SCRIPT:
   1. Tạo database
   2. USE database
   3. Xóa bảng cũ
   4. Tạo bảng
   5. Tạo khóa ngoại / index
   6. Insert dữ liệu mẫu
   7. Kiểm tra dữ liệu
   ========================================================= */


/* =========================================================
   1. TẠO DATABASE QLGiatUi
   ========================================================= */

USE master;
GO

IF DB_ID(N'QLGiatUi') IS NULL
BEGIN
    CREATE DATABASE QLGiatUi;
END
GO


/* =========================================================
   2. SỬ DỤNG DATABASE
   ========================================================= */

USE QLGiatUi;
GO


/* =========================================================
   3. XÓA CÁC BẢNG CŨ
   Chỉ xóa TABLE, không xóa DATABASE.
   ========================================================= */

IF OBJECT_ID(N'DanhGia', N'U') IS NOT NULL
    DROP TABLE DanhGia;
GO

IF OBJECT_ID(N'TinNhan', N'U') IS NOT NULL
    DROP TABLE TinNhan;
GO

IF OBJECT_ID(N'ThongBao', N'U') IS NOT NULL
    DROP TABLE ThongBao;
GO

IF OBJECT_ID(N'LichSuThayDoiHoaDon', N'U') IS NOT NULL
    DROP TABLE LichSuThayDoiHoaDon;
GO

IF OBJECT_ID(N'ThanhToan', N'U') IS NOT NULL
    DROP TABLE ThanhToan;
GO

IF OBJECT_ID(N'HoaDon', N'U') IS NOT NULL
    DROP TABLE HoaDon;
GO

IF OBJECT_ID(N'GiaoNhan', N'U') IS NOT NULL
    DROP TABLE GiaoNhan;
GO

IF OBJECT_ID(N'DiemTichLuy', N'U') IS NOT NULL
    DROP TABLE DiemTichLuy;
GO

IF OBJECT_ID(N'ChiTietDonHang', N'U') IS NOT NULL
    DROP TABLE ChiTietDonHang;
GO

IF OBJECT_ID(N'DonHang', N'U') IS NOT NULL
    DROP TABLE DonHang;
GO

IF OBJECT_ID(N'Booking', N'U') IS NOT NULL
    DROP TABLE Booking;
GO

IF OBJECT_ID(N'KhuyenMai', N'U') IS NOT NULL
    DROP TABLE KhuyenMai;
GO

IF OBJECT_ID(N'BangGia', N'U') IS NOT NULL
    DROP TABLE BangGia;
GO

IF OBJECT_ID(N'DonViTinh', N'U') IS NOT NULL
    DROP TABLE DonViTinh;
GO

IF OBJECT_ID(N'LoaiDoGiat', N'U') IS NOT NULL
    DROP TABLE LoaiDoGiat;
GO

IF OBJECT_ID(N'DichVu', N'U') IS NOT NULL
    DROP TABLE DichVu;
GO

IF OBJECT_ID(N'LoaiDichVu', N'U') IS NOT NULL
    DROP TABLE LoaiDichVu;
GO

IF OBJECT_ID(N'VaiTro_Quyen', N'U') IS NOT NULL
    DROP TABLE VaiTro_Quyen;
GO

IF OBJECT_ID(N'TaiKhoan_VaiTro', N'U') IS NOT NULL
    DROP TABLE TaiKhoan_VaiTro;
GO

IF OBJECT_ID(N'Quyen', N'U') IS NOT NULL
    DROP TABLE Quyen;
GO

IF OBJECT_ID(N'VaiTro', N'U') IS NOT NULL
    DROP TABLE VaiTro;
GO

IF OBJECT_ID(N'TaiKhoan', N'U') IS NOT NULL
    DROP TABLE TaiKhoan;
GO

IF OBJECT_ID(N'NhanVien', N'U') IS NOT NULL
    DROP TABLE NhanVien;
GO

IF OBJECT_ID(N'KhachHang', N'U') IS NOT NULL
    DROP TABLE KhachHang;
GO


/* =========================================================
   4. BẢNG KHÁCH HÀNG
   ========================================================= */

CREATE TABLE KhachHang
(
    KhachHangID INT IDENTITY(1,1) PRIMARY KEY,

    HoTen NVARCHAR(100) NOT NULL,

    SoDienThoai VARCHAR(15) NOT NULL,

    Email VARCHAR(150) NULL,

    DiaChi NVARCHAR(255) NULL,

    NgayTao DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_KhachHang_SoDienThoai
        UNIQUE (SoDienThoai),

    CONSTRAINT CK_KhachHang_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Khóa',
                N'Ngừng hoạt động'
            )
        )
);
GO


/* =========================================================
   5. BẢNG NHÂN VIÊN
   Chủ cửa hàng cũng là một nhân viên.
   ========================================================= */

CREATE TABLE NhanVien
(
    NhanVienID INT IDENTITY(1,1) PRIMARY KEY,

    HoTen NVARCHAR(100) NOT NULL,

    SoDienThoai VARCHAR(15) NOT NULL,

    Email VARCHAR(150) NULL,

    DiaChi NVARCHAR(255) NULL,

    ChucDanh NVARCHAR(100) NULL,

    NgayVaoLam DATE NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_NhanVien_SoDienThoai
        UNIQUE (SoDienThoai),

    CONSTRAINT CK_NhanVien_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Khóa',
                N'Ngừng hoạt động'
            )
        )
);
GO


/* =========================================================
   6. BẢNG VAI TRÒ
   Dynamic Role-Based Access Control
   ========================================================= */

CREATE TABLE VaiTro
(
    VaiTroID INT IDENTITY(1,1) PRIMARY KEY,

    TenVaiTro NVARCHAR(100) NOT NULL,

    MoTa NVARCHAR(255) NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_VaiTro_TenVaiTro
        UNIQUE (TenVaiTro),

    CONSTRAINT CK_VaiTro_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Ngừng hoạt động'
            )
        )
);
GO


/* =========================================================
   7. BẢNG QUYỀN
   ========================================================= */

CREATE TABLE Quyen
(
    QuyenID INT IDENTITY(1,1) PRIMARY KEY,

    MaQuyen VARCHAR(100) NOT NULL,

    TenQuyen NVARCHAR(150) NOT NULL,

    MoTa NVARCHAR(255) NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_Quyen_MaQuyen
        UNIQUE (MaQuyen),

    CONSTRAINT CK_Quyen_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Ngừng hoạt động'
            )
        )
);
GO


/* =========================================================
   8. BẢNG TÀI KHOẢN
   Một tài khoản thuộc:
   - Nhân viên
   HOẶC
   - Khách hàng
   ========================================================= */

CREATE TABLE TaiKhoan
(
    TaiKhoanID INT IDENTITY(1,1) PRIMARY KEY,

    TenDangNhap VARCHAR(100) NOT NULL,

    MatKhau VARCHAR(255) NOT NULL,

    Email VARCHAR(150) NULL,

    SoDienThoai VARCHAR(15) NULL,

    NhanVienID INT NULL,

    KhachHangID INT NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    NgayTao DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    CONSTRAINT UQ_TaiKhoan_TenDangNhap
        UNIQUE (TenDangNhap),

    CONSTRAINT FK_TaiKhoan_NhanVien
        FOREIGN KEY (NhanVienID)
        REFERENCES NhanVien(NhanVienID),

    CONSTRAINT FK_TaiKhoan_KhachHang
        FOREIGN KEY (KhachHangID)
        REFERENCES KhachHang(KhachHangID),

    CONSTRAINT CK_TaiKhoan_DoiTuong
        CHECK
        (
            (NhanVienID IS NOT NULL AND KhachHangID IS NULL)
            OR
            (NhanVienID IS NULL AND KhachHangID IS NOT NULL)
        ),

    CONSTRAINT CK_TaiKhoan_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Khóa',
                N'Ngừng hoạt động'
            )
        )
);
GO


/* =========================================================
   9. TÀI KHOẢN - VAI TRÒ
   ========================================================= */

CREATE TABLE TaiKhoan_VaiTro
(
    TaiKhoanID INT NOT NULL,

    VaiTroID INT NOT NULL,

    CONSTRAINT PK_TaiKhoan_VaiTro
        PRIMARY KEY
        (
            TaiKhoanID,
            VaiTroID
        ),

    CONSTRAINT FK_TaiKhoan_VaiTro_TaiKhoan
        FOREIGN KEY (TaiKhoanID)
        REFERENCES TaiKhoan(TaiKhoanID),

    CONSTRAINT FK_TaiKhoan_VaiTro_VaiTro
        FOREIGN KEY (VaiTroID)
        REFERENCES VaiTro(VaiTroID)
);
GO


/* =========================================================
   10. VAI TRÒ - QUYỀN
   ========================================================= */

CREATE TABLE VaiTro_Quyen
(
    VaiTroID INT NOT NULL,

    QuyenID INT NOT NULL,

    CONSTRAINT PK_VaiTro_Quyen
        PRIMARY KEY
        (
            VaiTroID,
            QuyenID
        ),

    CONSTRAINT FK_VaiTro_Quyen_VaiTro
        FOREIGN KEY (VaiTroID)
        REFERENCES VaiTro(VaiTroID),

    CONSTRAINT FK_VaiTro_Quyen_Quyen
        FOREIGN KEY (QuyenID)
        REFERENCES Quyen(QuyenID)
);
GO


/* =========================================================
   11. LOẠI DỊCH VỤ
   ========================================================= */

CREATE TABLE LoaiDichVu
(
    LoaiDichVuID INT IDENTITY(1,1) PRIMARY KEY,

    TenLoaiDichVu NVARCHAR(100) NOT NULL,

    MoTa NVARCHAR(255) NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_LoaiDichVu_Ten
        UNIQUE (TenLoaiDichVu),

    CONSTRAINT CK_LoaiDichVu_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Tạm ngưng'
            )
        )
);
GO


/* =========================================================
   12. DỊCH VỤ
   Không lưu đơn giá ở đây.
   Đơn giá nằm trong BangGia.
   ========================================================= */

CREATE TABLE DichVu
(
    DichVuID INT IDENTITY(1,1) PRIMARY KEY,

    LoaiDichVuID INT NOT NULL,

    TenDichVu NVARCHAR(150) NOT NULL,

    MoTa NVARCHAR(500) NULL,

    ThoiGianDuKien INT NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    NgayTao DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    CONSTRAINT FK_DichVu_LoaiDichVu
        FOREIGN KEY (LoaiDichVuID)
        REFERENCES LoaiDichVu(LoaiDichVuID),

    CONSTRAINT CK_DichVu_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Tạm ngưng'
            )
        )
);
GO


/* =========================================================
   13. LOẠI ĐỒ GIẶT
   ========================================================= */

CREATE TABLE LoaiDoGiat
(
    LoaiDoGiatID INT IDENTITY(1,1) PRIMARY KEY,

    TenLoaiDoGiat NVARCHAR(150) NOT NULL,

    MoTa NVARCHAR(255) NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_LoaiDoGiat_Ten
        UNIQUE (TenLoaiDoGiat),

    CONSTRAINT CK_LoaiDoGiat_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Tạm ngưng'
            )
        )
);
GO


/* =========================================================
   14. ĐƠN VỊ TÍNH
   ========================================================= */

CREATE TABLE DonViTinh
(
    DonViTinhID INT IDENTITY(1,1) PRIMARY KEY,

    TenDonViTinh NVARCHAR(50) NOT NULL,

    KyHieu VARCHAR(20) NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_DonViTinh_Ten
        UNIQUE (TenDonViTinh),

    CONSTRAINT CK_DonViTinh_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Tạm ngưng'
            )
        )
);
GO


/* =========================================================
   15. BẢNG GIÁ
   Giá phụ thuộc:
   Dịch vụ + Loại đồ giặt + Đơn vị tính
   ========================================================= */

CREATE TABLE BangGia
(
    BangGiaID INT IDENTITY(1,1) PRIMARY KEY,

    DichVuID INT NOT NULL,

    LoaiDoGiatID INT NOT NULL,

    DonViTinhID INT NOT NULL,

    DonGia DECIMAL(18,2) NOT NULL,

    NgayApDung DATE NOT NULL,

    NgayKetThuc DATE NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT FK_BangGia_DichVu
        FOREIGN KEY (DichVuID)
        REFERENCES DichVu(DichVuID),

    CONSTRAINT FK_BangGia_LoaiDoGiat
        FOREIGN KEY (LoaiDoGiatID)
        REFERENCES LoaiDoGiat(LoaiDoGiatID),

    CONSTRAINT FK_BangGia_DonViTinh
        FOREIGN KEY (DonViTinhID)
        REFERENCES DonViTinh(DonViTinhID),

    CONSTRAINT CK_BangGia_DonGia
        CHECK (DonGia >= 0),

    CONSTRAINT CK_BangGia_Ngay
        CHECK
        (
            NgayKetThuc IS NULL
            OR NgayKetThuc >= NgayApDung
        ),

    CONSTRAINT CK_BangGia_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Hết hiệu lực',
                N'Tạm ngưng'
            )
        )
);
GO


/* =========================================================
   16. KHUYẾN MÃI
   Tạo trước DonHang vì DonHang có FK KhuyenMaiID.
   ========================================================= */

CREATE TABLE KhuyenMai
(
    KhuyenMaiID INT IDENTITY(1,1) PRIMARY KEY,

    MaKhuyenMai VARCHAR(50) NOT NULL,

    TenKhuyenMai NVARCHAR(150) NOT NULL,

    LoaiKhuyenMai NVARCHAR(30) NOT NULL,

    GiaTriGiam DECIMAL(18,2) NOT NULL,

    GiaTriDonToiThieu DECIMAL(18,2) NULL,

    MucGiamToiDa DECIMAL(18,2) NULL,

    SoLuongSuDung INT NULL,

    DieuKienApDung NVARCHAR(500) NULL,

    NgayBatDau DATE NOT NULL,

    NgayKetThuc DATE NOT NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hoạt động',

    CONSTRAINT UQ_KhuyenMai_Ma
        UNIQUE (MaKhuyenMai),

    CONSTRAINT CK_KhuyenMai_Loai
        CHECK
        (
            LoaiKhuyenMai IN
            (
                N'Phần trăm',
                N'Tiền mặt'
            )
        ),

    CONSTRAINT CK_KhuyenMai_GiaTri
        CHECK (GiaTriGiam >= 0),

    CONSTRAINT CK_KhuyenMai_Ngay
        CHECK (NgayKetThuc >= NgayBatDau),

    CONSTRAINT CK_KhuyenMai_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hoạt động',
                N'Tạm ngưng',
                N'Hết hạn'
            )
        )
);
GO


/* =========================================================
   17. BOOKING
   Booking chỉ là lịch hẹn.
   Không chứa dịch vụ, loại đồ, số lượng, giá.
   ========================================================= */

CREATE TABLE Booking
(
    BookingID INT IDENTITY(1,1) PRIMARY KEY,

    MaBooking VARCHAR(30) NOT NULL,

    KhachHangID INT NOT NULL,

    HinhThucNhanDo NVARCHAR(30) NOT NULL,

    DiaChiNhan NVARCHAR(255) NULL,

    NgayHen DATE NOT NULL,

    GioHen TIME NOT NULL,

    GhiChu NVARCHAR(500) NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Chờ xác nhận',

    NgayTao DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    NgayCapNhat DATETIME2 NULL,

    CONSTRAINT UQ_Booking_Ma
        UNIQUE (MaBooking),

    CONSTRAINT FK_Booking_KhachHang
        FOREIGN KEY (KhachHangID)
        REFERENCES KhachHang(KhachHangID),

    CONSTRAINT CK_Booking_HinhThuc
        CHECK
        (
            HinhThucNhanDo IN
            (
                N'Tại cửa hàng',
                N'Tại nhà'
            )
        ),

    CONSTRAINT CK_Booking_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Chờ xác nhận',
                N'Đã xác nhận',
                N'Đã hủy',
                N'Hoàn thành'
            )
        )
);
GO


/* =========================================================
   18. ĐƠN HÀNG
   ========================================================= */

CREATE TABLE DonHang
(
    DonHangID INT IDENTITY(1,1) PRIMARY KEY,

    MaDonHang VARCHAR(30) NOT NULL,

    BookingID INT NULL,

    KhachHangID INT NOT NULL,

    NhanVienID INT NULL,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Chờ tiếp nhận',

    TongTien DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    DiemSuDung INT NOT NULL
        DEFAULT 0,

    TienGiamDoDiem DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    KhuyenMaiID INT NULL,

    TienGiamKhuyenMai DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    PhiGiaoHang DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    ThanhTien DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    GhiChu NVARCHAR(500) NULL,

    NgayTao DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    NgayCapNhat DATETIME2 NULL,

    CONSTRAINT UQ_DonHang_Ma
        UNIQUE (MaDonHang),

    CONSTRAINT FK_DonHang_Booking
        FOREIGN KEY (BookingID)
        REFERENCES Booking(BookingID),

    CONSTRAINT FK_DonHang_KhachHang
        FOREIGN KEY (KhachHangID)
        REFERENCES KhachHang(KhachHangID),

    CONSTRAINT FK_DonHang_NhanVien
        FOREIGN KEY (NhanVienID)
        REFERENCES NhanVien(NhanVienID),

    CONSTRAINT FK_DonHang_KhuyenMai
        FOREIGN KEY (KhuyenMaiID)
        REFERENCES KhuyenMai(KhuyenMaiID),

    CONSTRAINT CK_DonHang_Tien
        CHECK
        (
            TongTien >= 0
            AND DiemSuDung >= 0
            AND TienGiamDoDiem >= 0
            AND TienGiamKhuyenMai >= 0
            AND PhiGiaoHang >= 0
            AND ThanhTien >= 0
        ),

    CONSTRAINT CK_DonHang_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Chờ tiếp nhận',
                N'Đã tiếp nhận',
                N'Đang giặt',
                N'Hoàn thành giặt',
                N'Đang giao',
                N'Đã giao',
                N'Đã thanh toán',
                N'Đã hủy'
            )
        )
);
GO


/* =========================================================
   19. INDEX BOOKINGID
   Một booking chỉ tạo tối đa một đơn.
   Cho phép nhiều đơn có BookingID NULL.
   ========================================================= */

CREATE UNIQUE INDEX UX_DonHang_BookingID
ON DonHang(BookingID)
WHERE BookingID IS NOT NULL;
GO


/* =========================================================
   20. CHI TIẾT ĐƠN HÀNG
   Một đơn có thể có nhiều:
   - Loại đồ
   - Dịch vụ
   ========================================================= */

CREATE TABLE ChiTietDonHang
(
    ChiTietDonHangID INT IDENTITY(1,1) PRIMARY KEY,

    DonHangID INT NOT NULL,

    DichVuID INT NOT NULL,

    LoaiDoGiatID INT NOT NULL,

    DonViTinhID INT NOT NULL,

    SoLuong DECIMAL(10,2) NULL,

    KhoiLuong DECIMAL(10,2) NULL,

    DonGia DECIMAL(18,2) NOT NULL,

    ThanhTien DECIMAL(18,2) NOT NULL,

    GhiChu NVARCHAR(500) NULL,

    CONSTRAINT FK_CTDH_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID),

    CONSTRAINT FK_CTDH_DichVu
        FOREIGN KEY (DichVuID)
        REFERENCES DichVu(DichVuID),

    CONSTRAINT FK_CTDH_LoaiDoGiat
        FOREIGN KEY (LoaiDoGiatID)
        REFERENCES LoaiDoGiat(LoaiDoGiatID),

    CONSTRAINT FK_CTDH_DonViTinh
        FOREIGN KEY (DonViTinhID)
        REFERENCES DonViTinh(DonViTinhID),

    CONSTRAINT CK_CTDH_SoLuongKhoiLuong
        CHECK
        (
            (SoLuong IS NOT NULL AND SoLuong > 0)
            OR
            (KhoiLuong IS NOT NULL AND KhoiLuong > 0)
        ),

    CONSTRAINT CK_CTDH_DonGia
        CHECK (DonGia >= 0),

    CONSTRAINT CK_CTDH_ThanhTien
        CHECK (ThanhTien >= 0)
);
GO


/* =========================================================
   21. ĐIỂM TÍCH LŨY
   ========================================================= */

CREATE TABLE DiemTichLuy
(
    DiemTichLuyID INT IDENTITY(1,1) PRIMARY KEY,

    KhachHangID INT NOT NULL,

    DiemHienTai INT NOT NULL
        DEFAULT 0,

    NgayCapNhat DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    CONSTRAINT UQ_DiemTichLuy_KhachHang
        UNIQUE (KhachHangID),

    CONSTRAINT FK_DiemTichLuy_KhachHang
        FOREIGN KEY (KhachHangID)
        REFERENCES KhachHang(KhachHangID),

    CONSTRAINT CK_DiemTichLuy_Diem
        CHECK (DiemHienTai >= 0)
);
GO


/* =========================================================
   22. GIAO NHẬN
   Một đơn có thể có:
   - NHAN_DO
   - GIAO_DO
   ========================================================= */

CREATE TABLE GiaoNhan
(
    GiaoNhanID INT IDENTITY(1,1) PRIMARY KEY,

    DonHangID INT NOT NULL,

    NhanVienID INT NULL,

    LoaiGiaoNhan NVARCHAR(30) NOT NULL,

    HinhThuc NVARCHAR(30) NOT NULL,

    DiaChi NVARCHAR(255) NULL,

    ThoiGianDuKien DATETIME2 NULL,

    ThoiGianThucTe DATETIME2 NULL,

    PhiGiaoNhan DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Chờ thực hiện',

    GhiChu NVARCHAR(500) NULL,

    CONSTRAINT FK_GiaoNhan_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID),

    CONSTRAINT FK_GiaoNhan_NhanVien
        FOREIGN KEY (NhanVienID)
        REFERENCES NhanVien(NhanVienID),

    CONSTRAINT CK_GiaoNhan_Loai
        CHECK
        (
            LoaiGiaoNhan IN
            (
                N'NHAN_DO',
                N'GIAO_DO'
            )
        ),

    CONSTRAINT CK_GiaoNhan_HinhThuc
        CHECK
        (
            HinhThuc IN
            (
                N'Tại cửa hàng',
                N'Tại nhà'
            )
        ),

    CONSTRAINT CK_GiaoNhan_Phi
        CHECK (PhiGiaoNhan >= 0),

    CONSTRAINT CK_GiaoNhan_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Chờ thực hiện',
                N'Đang thực hiện',
                N'Hoàn thành',
                N'Đã hủy'
            )
        )
);
GO


/* =========================================================
   23. HÓA ĐƠN
   ========================================================= */

CREATE TABLE HoaDon
(
    HoaDonID INT IDENTITY(1,1) PRIMARY KEY,

    MaHoaDon VARCHAR(30) NOT NULL,

    DonHangID INT NOT NULL,

    TongTien DECIMAL(18,2) NOT NULL,

    GiamGia DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    PhiGiaoHang DECIMAL(18,2) NOT NULL
        DEFAULT 0,

    ThanhTien DECIMAL(18,2) NOT NULL,

    NgayLap DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Chưa thanh toán',

    CONSTRAINT UQ_HoaDon_Ma
        UNIQUE (MaHoaDon),

    CONSTRAINT UQ_HoaDon_DonHang
        UNIQUE (DonHangID),

    CONSTRAINT FK_HoaDon_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID),

    CONSTRAINT CK_HoaDon_Tien
        CHECK
        (
            TongTien >= 0
            AND GiamGia >= 0
            AND PhiGiaoHang >= 0
            AND ThanhTien >= 0
        ),

    CONSTRAINT CK_HoaDon_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Chưa thanh toán',
                N'Đã thanh toán',
                N'Đã hủy'
            )
        )
);
GO


/* =========================================================
   24. THANH TOÁN
   ========================================================= */

CREATE TABLE ThanhToan
(
    ThanhToanID INT IDENTITY(1,1) PRIMARY KEY,

    DonHangID INT NOT NULL,

    SoTien DECIMAL(18,2) NOT NULL,

    PhuongThuc NVARCHAR(30) NOT NULL,

    MaGiaoDich VARCHAR(100) NULL,

    ThoiGian DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Chờ thanh toán',

    GhiChu NVARCHAR(500) NULL,

    CONSTRAINT FK_ThanhToan_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID),

    CONSTRAINT CK_ThanhToan_SoTien
        CHECK (SoTien > 0),

    CONSTRAINT CK_ThanhToan_PhuongThuc
        CHECK
        (
            PhuongThuc IN
            (
                N'Tiền mặt',
                N'Chuyển khoản'
            )
        ),

    CONSTRAINT CK_ThanhToan_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Chờ thanh toán',
                N'Thành công',
                N'Thất bại',
                N'Đã hoàn tiền'
            )
        )
);
GO


/* =========================================================
   25. LỊCH SỬ THAY ĐỔI HÓA ĐƠN
   Chủ cửa hàng có thể chỉnh sửa dữ liệu tài chính.
   ========================================================= */

CREATE TABLE LichSuThayDoiHoaDon
(
    LichSuID INT IDENTITY(1,1) PRIMARY KEY,

    HoaDonID INT NOT NULL,

    TaiKhoanID INT NOT NULL,

    ThoiGian DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    TruongThayDoi VARCHAR(100) NOT NULL,

    GiaTriCu NVARCHAR(500) NULL,

    GiaTriMoi NVARCHAR(500) NULL,

    LyDo NVARCHAR(500) NULL,

    CONSTRAINT FK_LichSu_HoaDon
        FOREIGN KEY (HoaDonID)
        REFERENCES HoaDon(HoaDonID),

    CONSTRAINT FK_LichSu_TaiKhoan
        FOREIGN KEY (TaiKhoanID)
        REFERENCES TaiKhoan(TaiKhoanID)
);
GO


/* =========================================================
   26. THÔNG BÁO
   ========================================================= */

CREATE TABLE ThongBao
(
    ThongBaoID INT IDENTITY(1,1) PRIMARY KEY,

    TaiKhoanID INT NOT NULL,

    DonHangID INT NULL,

    LoaiThongBao NVARCHAR(50) NULL,

    TieuDe NVARCHAR(200) NOT NULL,

    NoiDung NVARCHAR(1000) NOT NULL,

    ThoiGianGui DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    DaDoc BIT NOT NULL
        DEFAULT 0,

    CONSTRAINT FK_ThongBao_TaiKhoan
        FOREIGN KEY (TaiKhoanID)
        REFERENCES TaiKhoan(TaiKhoanID),

    CONSTRAINT FK_ThongBao_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID)
);
GO


/* =========================================================
   27. TIN NHẮN
   ========================================================= */

CREATE TABLE TinNhan
(
    TinNhanID INT IDENTITY(1,1) PRIMARY KEY,

    NguoiGuiID INT NOT NULL,

    NguoiNhanID INT NOT NULL,

    DonHangID INT NULL,

    NoiDung NVARCHAR(1000) NOT NULL,

    ThoiGianGui DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Đã gửi',

    CONSTRAINT FK_TinNhan_NguoiGui
        FOREIGN KEY (NguoiGuiID)
        REFERENCES TaiKhoan(TaiKhoanID),

    CONSTRAINT FK_TinNhan_NguoiNhan
        FOREIGN KEY (NguoiNhanID)
        REFERENCES TaiKhoan(TaiKhoanID),

    CONSTRAINT FK_TinNhan_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID),

    CONSTRAINT CK_TinNhan_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Đã gửi',
                N'Đã nhận',
                N'Đã đọc'
            )
        )
);
GO


/* =========================================================
   28. ĐÁNH GIÁ
   ========================================================= */

CREATE TABLE DanhGia
(
    DanhGiaID INT IDENTITY(1,1) PRIMARY KEY,

    DonHangID INT NOT NULL,

    KhachHangID INT NOT NULL,

    SoSao INT NOT NULL,

    BinhLuan NVARCHAR(1000) NULL,

    NgayDanhGia DATETIME2 NOT NULL
        DEFAULT SYSDATETIME(),

    TrangThai NVARCHAR(30) NOT NULL
        DEFAULT N'Hiển thị',

    CONSTRAINT UQ_DanhGia_DonHang
        UNIQUE (DonHangID),

    CONSTRAINT FK_DanhGia_DonHang
        FOREIGN KEY (DonHangID)
        REFERENCES DonHang(DonHangID),

    CONSTRAINT FK_DanhGia_KhachHang
        FOREIGN KEY (KhachHangID)
        REFERENCES KhachHang(KhachHangID),

    CONSTRAINT CK_DanhGia_SoSao
        CHECK (SoSao BETWEEN 1 AND 5),

    CONSTRAINT CK_DanhGia_TrangThai
        CHECK
        (
            TrangThai IN
            (
                N'Hiển thị',
                N'Ẩn'
            )
        )
);
GO


/* =========================================================
   29. INDEX
   ========================================================= */

CREATE INDEX IX_DonHang_KhachHangID
ON DonHang(KhachHangID);
GO

CREATE INDEX IX_DonHang_NhanVienID
ON DonHang(NhanVienID);
GO

CREATE INDEX IX_DonHang_TrangThai
ON DonHang(TrangThai);
GO

CREATE INDEX IX_ChiTietDonHang_DonHangID
ON ChiTietDonHang(DonHangID);
GO

CREATE INDEX IX_GiaoNhan_DonHangID
ON GiaoNhan(DonHangID);
GO

CREATE INDEX IX_ThanhToan_DonHangID
ON ThanhToan(DonHangID);
GO

CREATE INDEX IX_Booking_KhachHangID
ON Booking(KhachHangID);
GO


/* =========================================================
   30. DỮ LIỆU MẪU - VAI TRÒ
   ========================================================= */

INSERT INTO VaiTro
(
    TenVaiTro,
    MoTa,
    TrangThai
)
VALUES
(
    N'Chủ cửa hàng',
    N'Có toàn quyền quản lý hệ thống và được phép can thiệp dữ liệu đã hoàn thành.',
    N'Hoạt động'
),
(
    N'Quản lý',
    N'Quản lý hoạt động cửa hàng.',
    N'Hoạt động'
),
(
    N'Nhân viên',
    N'Thực hiện các nghiệp vụ tại cửa hàng.',
    N'Hoạt động'
),
(
    N'Khách hàng',
    N'Sử dụng ứng dụng để đặt và theo dõi dịch vụ.',
    N'Hoạt động'
);
GO


/* =========================================================
   31. DỮ LIỆU MẪU - QUYỀN
   ========================================================= */

INSERT INTO Quyen
(
    MaQuyen,
    TenQuyen,
    MoTa,
    TrangThai
)
VALUES
(
    'DASHBOARD_VIEW',
    N'Xem Dashboard',
    N'Xem thông tin tổng quan.',
    N'Hoạt động'
),
(
    'ORDER_VIEW',
    N'Xem đơn hàng',
    N'Xem danh sách và chi tiết đơn hàng.',
    N'Hoạt động'
),
(
    'ORDER_CREATE',
    N'Tạo đơn hàng',
    N'Tạo đơn hàng mới.',
    N'Hoạt động'
),
(
    'ORDER_UPDATE',
    N'Cập nhật đơn hàng',
    N'Cập nhật đơn hàng khi được phép.',
    N'Hoạt động'
),
(
    'ORDER_DELETE',
    N'Xóa đơn hàng',
    N'Xóa đơn hàng khi được phép.',
    N'Hoạt động'
),
(
    'SERVICE_MANAGE',
    N'Quản lý dịch vụ',
    N'Thêm, sửa, ngừng hoạt động dịch vụ.',
    N'Hoạt động'
),
(
    'PRICE_MANAGE',
    N'Quản lý bảng giá',
    N'Quản lý giá theo dịch vụ và loại đồ.',
    N'Hoạt động'
),
(
    'PROMOTION_MANAGE',
    N'Quản lý khuyến mãi',
    N'Quản lý chương trình khuyến mãi.',
    N'Hoạt động'
),
(
    'CUSTOMER_VIEW',
    N'Xem khách hàng',
    N'Xem thông tin khách hàng.',
    N'Hoạt động'
),
(
    'CUSTOMER_MANAGE',
    N'Quản lý khách hàng',
    N'Quản lý thông tin khách hàng.',
    N'Hoạt động'
),
(
    'DELIVERY_MANAGE',
    N'Quản lý giao nhận',
    N'Quản lý nhận đồ và giao đồ.',
    N'Hoạt động'
),
(
    'REPORT_VIEW',
    N'Xem báo cáo',
    N'Xem báo cáo kinh doanh.',
    N'Hoạt động'
),
(
    'INVOICE_VIEW',
    N'Xem hóa đơn',
    N'Xem hóa đơn.',
    N'Hoạt động'
),
(
    'INVOICE_UPDATE',
    N'Cập nhật hóa đơn',
    N'Chỉnh sửa hóa đơn khi được phép.',
    N'Hoạt động'
),
(
    'PAYMENT_CREATE',
    N'Tạo thanh toán',
    N'Tạo giao dịch thanh toán.',
    N'Hoạt động'
),
(
    'ACCOUNT_MANAGE',
    N'Quản lý tài khoản',
    N'Quản lý tài khoản.',
    N'Hoạt động'
),
(
    'ROLE_MANAGE',
    N'Quản lý vai trò',
    N'Tạo vai trò và phân quyền.',
    N'Hoạt động'
),
(
    'SYSTEM_FULL_ACCESS',
    N'Toàn quyền hệ thống',
    N'Toàn quyền quản trị.',
    N'Hoạt động'
);
GO


/* =========================================================
   32. PHÂN QUYỀN CHO CHỦ CỬA HÀNG
   ========================================================= */

INSERT INTO VaiTro_Quyen
(
    VaiTroID,
    QuyenID
)
SELECT
    1,
    QuyenID
FROM Quyen;
GO


/* =========================================================
   33. PHÂN QUYỀN CHO QUẢN LÝ
   ========================================================= */

INSERT INTO VaiTro_Quyen
(
    VaiTroID,
    QuyenID
)
SELECT
    2,
    QuyenID
FROM Quyen
WHERE MaQuyen IN
(
    'DASHBOARD_VIEW',
    'ORDER_VIEW',
    'ORDER_CREATE',
    'ORDER_UPDATE',
    'SERVICE_MANAGE',
    'PRICE_MANAGE',
    'PROMOTION_MANAGE',
    'CUSTOMER_VIEW',
    'CUSTOMER_MANAGE',
    'DELIVERY_MANAGE',
    'REPORT_VIEW',
    'INVOICE_VIEW',
    'PAYMENT_CREATE'
);
GO


/* =========================================================
   34. PHÂN QUYỀN CHO NHÂN VIÊN
   ========================================================= */

INSERT INTO VaiTro_Quyen
(
    VaiTroID,
    QuyenID
)
SELECT
    3,
    QuyenID
FROM Quyen
WHERE MaQuyen IN
(
    'DASHBOARD_VIEW',
    'ORDER_VIEW',
    'ORDER_CREATE',
    'ORDER_UPDATE',
    'CUSTOMER_VIEW',
    'DELIVERY_MANAGE',
    'INVOICE_VIEW',
    'PAYMENT_CREATE'
);
GO


/* =========================================================
   35. PHÂN QUYỀN CHO KHÁCH HÀNG
   ========================================================= */

INSERT INTO VaiTro_Quyen
(
    VaiTroID,
    QuyenID
)
SELECT
    4,
    QuyenID
FROM Quyen
WHERE MaQuyen IN
(
    'ORDER_VIEW',
    'ORDER_CREATE'
);
GO


/* =========================================================
   36. DỮ LIỆU NHÂN VIÊN
   ========================================================= */

INSERT INTO NhanVien
(
    HoTen,
    SoDienThoai,
    Email,
    DiaChi,
    ChucDanh,
    NgayVaoLam,
    TrangThai
)
VALUES
(
    N'Lê Khôi Nguyên',
    '0900000001',
    'owner@giatui.vn',
    N'TP. Hồ Chí Minh',
    N'Chủ cửa hàng',
    '2024-01-01',
    N'Hoạt động'
),
(
    N'Nguyễn Văn An',
    '0900000002',
    'an@giatui.vn',
    N'TP. Hồ Chí Minh',
    N'Quản lý',
    '2024-02-01',
    N'Hoạt động'
),
(
    N'Trần Văn Bình',
    '0900000003',
    'binh@giatui.vn',
    N'TP. Hồ Chí Minh',
    N'Nhân viên',
    '2024-03-01',
    N'Hoạt động'
),
(
    N'Phạm Văn Cường',
    '0900000004',
    'cuong@giatui.vn',
    N'TP. Hồ Chí Minh',
    N'Nhân viên',
    '2024-04-01',
    N'Hoạt động'
);
GO


/* =========================================================
   37. DỮ LIỆU KHÁCH HÀNG
   ========================================================= */

INSERT INTO KhachHang
(
    HoTen,
    SoDienThoai,
    Email,
    DiaChi,
    TrangThai
)
VALUES
(
    N'Nguyễn Thị Lan',
    '0911000001',
    'lan@gmail.com',
    N'12 Nguyễn Trãi, Quận 1, TP.HCM',
    N'Hoạt động'
),
(
    N'Trần Minh Anh',
    '0911000002',
    'minhanh@gmail.com',
    N'25 Lê Lợi, Quận 1, TP.HCM',
    N'Hoạt động'
),
(
    N'Lê Văn Nam',
    '0911000003',
    'nam@gmail.com',
    N'50 Nguyễn Đình Chiểu, Quận 3, TP.HCM',
    N'Hoạt động'
),
(
    N'Phạm Thị Hương',
    '0911000004',
    'huong@gmail.com',
    N'100 Điện Biên Phủ, Bình Thạnh, TP.HCM',
    N'Hoạt động'
),
(
    N'Hoàng Minh Đức',
    '0911000005',
    'duc@gmail.com',
    N'20 Phan Văn Trị, Gò Vấp, TP.HCM',
    N'Hoạt động'
);
GO


/* =========================================================
   38. DỮ LIỆU TÀI KHOẢN
   =========================================================

   Tất cả tài khoản mẫu:

   Username:
   owner
   quanly
   nhanvien1
   nhanvien2
   khachhang1
   khachhang2
   khachhang3
   khachhang4
   khachhang5

   Password:
   123456

   BCrypt hash của 123456:
   $2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq

   ========================================================= */

INSERT INTO TaiKhoan
(
    TenDangNhap,
    MatKhau,
    Email,
    SoDienThoai,
    NhanVienID,
    KhachHangID,
    TrangThai
)
VALUES
(
    'owner',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'owner@giatui.vn',
    '0900000001',
    1,
    NULL,
    N'Hoạt động'
),
(
    'quanly',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'an@giatui.vn',
    '0900000002',
    2,
    NULL,
    N'Hoạt động'
),
(
    'nhanvien1',
    '$2y$12$8VtxLXHd1w4v5FMVCoj3O2u0hedxrKG3m0qqgfkN.M79uUq',
    'binh@giatui.vn',
    '0900000003',
    3,
    NULL,
    N'Hoạt động'
),
(
    'nhanvien2',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'cuong@giatui.vn',
    '0900000004',
    4,
    NULL,
    N'Hoạt động'
),
(
    'khachhang1',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'lan@gmail.com',
    '0911000001',
    NULL,
    1,
    N'Hoạt động'
),
(
    'khachhang2',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'minhanh@gmail.com',
    '0911000002',
    NULL,
    2,
    N'Hoạt động'
),
(
    'khachhang3',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'nam@gmail.com',
    '0911000003',
    NULL,
    3,
    N'Hoạt động'
),
(
    'khachhang4',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'huong@gmail.com',
    '0911000004',
    NULL,
    4,
    N'Hoạt động'
),
(
    'khachhang5',
    '$2y$12$8VtxLXHd1w4v5FMVCojcGOz72Ko2u0hedxrKG3m0qqgfkN.M79uUq',
    'duc@gmail.com',
    '0911000005',
    NULL,
    5,
    N'Hoạt động'
);
GO


/* =========================================================
   39. GÁN VAI TRÒ CHO TÀI KHOẢN
   ========================================================= */

INSERT INTO TaiKhoan_VaiTro
(
    TaiKhoanID,
    VaiTroID
)
VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 3),
(5, 4),
(6, 4),
(7, 4),
(8, 4),
(9, 4);
GO


/* =========================================================
   40. LOẠI DỊCH VỤ
   ========================================================= */

INSERT INTO LoaiDichVu
(
    TenLoaiDichVu,
    MoTa,
    TrangThai
)
VALUES
(
    N'Giặt',
    N'Các dịch vụ giặt.',
    N'Hoạt động'
),
(
    N'Sấy',
    N'Các dịch vụ sấy khô.',
    N'Hoạt động'
),
(
    N'Ủi',
    N'Các dịch vụ ủi.',
    N'Hoạt động'
),
(
    N'Giặt khô',
    N'Dịch vụ giặt khô.',
    N'Hoạt động'
);
GO


/* =========================================================
   41. DỊCH VỤ
   ========================================================= */

INSERT INTO DichVu
(
    LoaiDichVuID,
    TenDichVu,
    MoTa,
    ThoiGianDuKien,
    TrangThai
)
VALUES
(
    1,
    N'Giặt thường',
    N'Giặt thông thường.',
    45,
    N'Hoạt động'
),
(
    1,
    N'Giặt kỹ',
    N'Giặt kỹ cho đồ bẩn nhiều.',
    60,
    N'Hoạt động'
),
(
    2,
    N'Sấy khô',
    N'Sấy khô quần áo.',
    60,
    N'Hoạt động'
),
(
    3,
    N'Ủi thường',
    N'Ủi quần áo.',
    30,
    N'Hoạt động'
),
(
    4,
    N'Giặt khô',
    N'Giặt khô cho đồ đặc biệt.',
    120,
    N'Hoạt động'
);
GO


/* =========================================================
   42. LOẠI ĐỒ GIẶT
   ========================================================= */

INSERT INTO LoaiDoGiat
(
    TenLoaiDoGiat,
    MoTa,
    TrangThai
)
VALUES
(
    N'Quần áo',
    N'Áo, quần và quần áo thông thường.',
    N'Hoạt động'
),
(
    N'Chăn',
    N'Các loại chăn.',
    N'Hoạt động'
),
(
    N'Ga giường',
    N'Ga giường và drap.',
    N'Hoạt động'
),
(
    N'Giày',
    N'Các loại giày.',
    N'Hoạt động'
),
(
    N'Gấu bông',
    N'Gấu bông và đồ chơi.',
    N'Hoạt động'
),
(
    N'Áo dài',
    N'Áo dài và trang phục đặc biệt.',
    N'Hoạt động'
);
GO


/* =========================================================
   43. ĐƠN VỊ TÍNH
   ========================================================= */

INSERT INTO DonViTinh
(
    TenDonViTinh,
    KyHieu,
    TrangThai
)
VALUES
(
    N'Kilogram',
    'kg',
    N'Hoạt động'
),
(
    N'Cái',
    'cái',
    N'Hoạt động'
),
(
    N'Đôi',
    'đôi',
    N'Hoạt động'
),
(
    N'Bộ',
    'bộ',
    N'Hoạt động'
);
GO


/* =========================================================
   44. BẢNG GIÁ
   ========================================================= */

INSERT INTO BangGia
(
    DichVuID,
    LoaiDoGiatID,
    DonViTinhID,
    DonGia,
    NgayApDung,
    NgayKetThuc,
    TrangThai
)
VALUES
(
    1,
    1,
    1,
    10000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    2,
    1,
    1,
    15000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    3,
    1,
    1,
    10000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    4,
    1,
    2,
    10000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    1,
    2,
    2,
    22000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    1,
    3,
    2,
    22000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    1,
    4,
    3,
    50000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    1,
    5,
    2,
    30000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    5,
    6,
    4,
    80000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
),
(
    4,
    6,
    4,
    30000,
    '2026-01-01',
    NULL,
    N'Hoạt động'
);
GO


/* =========================================================
   45. KHUYẾN MÃI
   ========================================================= */

INSERT INTO KhuyenMai
(
    MaKhuyenMai,
    TenKhuyenMai,
    LoaiKhuyenMai,
    GiaTriGiam,
    GiaTriDonToiThieu,
    MucGiamToiDa,
    SoLuongSuDung,
    DieuKienApDung,
    NgayBatDau,
    NgayKetThuc,
    TrangThai
)
VALUES
(
    'GIATUI10',
    N'Giảm 10% đơn hàng',
    N'Phần trăm',
    10,
    100000,
    50000,
    100,
    N'Áp dụng cho đơn hàng từ 100.000đ.',
    '2026-01-01',
    '2026-12-31',
    N'Hoạt động'
),
(
    'GIAM20K',
    N'Giảm 20.000đ',
    N'Tiền mặt',
    20000,
    150000,
    NULL,
    50,
    N'Áp dụng cho đơn hàng từ 150.000đ.',
    '2026-01-01',
    '2026-12-31',
    N'Hoạt động'
),
(
    'CHAOHE',
    N'Khuyến mãi mùa hè',
    N'Phần trăm',
    15,
    200000,
    60000,
    100,
    N'Áp dụng cho đơn hàng từ 200.000đ.',
    '2026-05-01',
    '2026-09-30',
    N'Hoạt động'
);
GO


/* =========================================================
   46. ĐIỂM TÍCH LŨY
   ========================================================= */

INSERT INTO DiemTichLuy
(
    KhachHangID,
    DiemHienTai
)
VALUES
(1, 120),
(2, 80),
(3, 250),
(4, 40),
(5, 160);
GO


/* =========================================================
   47. BOOKING
   ========================================================= */

INSERT INTO Booking
(
    MaBooking,
    KhachHangID,
    HinhThucNhanDo,
    DiaChiNhan,
    NgayHen,
    GioHen,
    GhiChu,
    TrangThai,
    NgayCapNhat
)
VALUES
(
    'BK0001',
    1,
    N'Tại nhà',
    N'12 Nguyễn Trãi, Quận 1, TP.HCM',
    '2026-09-25',
    '09:00',
    N'Khách muốn nhận đồ tại nhà.',
    N'Đã xác nhận',
    SYSDATETIME()
),
(
    'BK0002',
    2,
    N'Tại cửa hàng',
    NULL,
    '2026-09-25',
    '10:30',
    N'Khách mang đồ đến cửa hàng.',
    N'Chờ xác nhận',
    NULL
),
(
    'BK0003',
    4,
    N'Tại nhà',
    N'100 Điện Biên Phủ, Bình Thạnh, TP.HCM',
    '2026-09-26',
    '14:00',
    N'Nhận đồ tại nhà.',
    N'Chờ xác nhận',
    NULL
);
GO


/* =========================================================
   48. ĐƠN HÀNG
   ========================================================= */

INSERT INTO DonHang
(
    MaDonHang,
    BookingID,
    KhachHangID,
    NhanVienID,
    TrangThai,
    TongTien,
    DiemSuDung,
    TienGiamDoDiem,
    KhuyenMaiID,
    TienGiamKhuyenMai,
    PhiGiaoHang,
    ThanhTien,
    GhiChu,
    NgayTao,
    NgayCapNhat
)
VALUES
(
    'DH0001',
    1,
    1,
    3,
    N'Đã thanh toán',
    180000,
    20,
    20000,
    1,
    16000,
    0,
    144000,
    N'Đơn nhận đồ tại nhà.',
    '2026-09-25 09:10:00',
    '2026-09-26 15:00:00'
),
(
    'DH0002',
    NULL,
    2,
    4,
    N'Đang giặt',
    120000,
    0,
    0,
    NULL,
    0,
    0,
    120000,
    N'Khách mang đồ trực tiếp đến cửa hàng.',
    '2026-09-24 10:00:00',
    '2026-09-24 11:00:00'
),
(
    'DH0003',
    NULL,
    3,
    3,
    N'Đã giao',
    250000,
    30,
    30000,
    2,
    20000,
    20000,
    220000,
    N'Đã giao đồ về nhà.',
    '2026-09-23 08:30:00',
    '2026-09-24 17:30:00'
),
(
    'DH0004',
    NULL,
    4,
    4,
    N'Chờ tiếp nhận',
    0,
    0,
    0,
    NULL,
    0,
    0,
    0,
    N'Đơn mới từ khách hàng.',
    '2026-09-27 08:00:00',
    NULL
);
GO


/* =========================================================
   49. CHI TIẾT ĐƠN HÀNG
   ========================================================= */

INSERT INTO ChiTietDonHang
(
    DonHangID,
    DichVuID,
    LoaiDoGiatID,
    DonViTinhID,
    SoLuong,
    KhoiLuong,
    DonGia,
    ThanhTien,
    GhiChu
)
VALUES
(
    1,
    1,
    1,
    1,
    NULL,
    8,
    10000,
    80000,
    N'Quần áo thường'
),
(
    1,
    3,
    1,
    1,
    NULL,
    8,
    10000,
    80000,
    N'Sấy khô'
),
(
    1,
    4,
    1,
    2,
    2,
    NULL,
    10000,
    20000,
    N'Ủi 2 cái'
),
(
    2,
    1,
    1,
    1,
    NULL,
    7,
    10000,
    70000,
    N'Quần áo'
),
(
    2,
    3,
    1,
    1,
    NULL,
    5,
    10000,
    50000,
    N'Sấy'
),
(
    3,
    1,
    2,
    2,
    1,
    NULL,
    22000,
    22000,
    N'Chăn'
),
(
    3,
    1,
    3,
    2,
    2,
    NULL,
    22000,
    44000,
    N'Ga giường'
),
(
    3,
    1,
    4,
    3,
    1,
    NULL,
    50000,
    50000,
    N'Giày'
),
(
    3,
    5,
    6,
    4,
    1,
    NULL,
    80000,
    80000,
    N'Áo dài'
);
GO


/* =========================================================
   50. GIAO NHẬN
   ========================================================= */

INSERT INTO GiaoNhan
(
    DonHangID,
    NhanVienID,
    LoaiGiaoNhan,
    HinhThuc,
    DiaChi,
    ThoiGianDuKien,
    ThoiGianThucTe,
    PhiGiaoNhan,
    TrangThai,
    GhiChu
)
VALUES
(
    1,
    3,
    N'NHAN_DO',
    N'Tại nhà',
    N'12 Nguyễn Trãi, Quận 1, TP.HCM',
    '2026-09-25 09:00:00',
    '2026-09-25 09:10:00',
    0,
    N'Hoàn thành',
    N'Đã nhận đủ đồ.'
),
(
    1,
    3,
    N'GIAO_DO',
    N'Tại nhà',
    N'12 Nguyễn Trãi, Quận 1, TP.HCM',
    '2026-09-26 15:00:00',
    '2026-09-26 15:00:00',
    0,
    N'Hoàn thành',
    N'Đã giao đủ đồ.'
),
(
    2,
    4,
    N'NHAN_DO',
    N'Tại cửa hàng',
    NULL,
    '2026-09-24 10:00:00',
    '2026-09-24 10:00:00',
    0,
    N'Hoàn thành',
    N'Khách mang đồ đến cửa hàng.'
),
(
    3,
    3,
    N'NHAN_DO',
    N'Tại cửa hàng',
    NULL,
    '2026-09-23 08:30:00',
    '2026-09-23 08:30:00',
    0,
    N'Hoàn thành',
    N'Khách mang đồ đến.'
),
(
    3,
    3,
    N'GIAO_DO',
    N'Tại nhà',
    N'50 Nguyễn Đình Chiểu, Quận 3, TP.HCM',
    '2026-09-24 17:00:00',
    '2026-09-24 17:30:00',
    20000,
    N'Hoàn thành',
    N'Đã giao và thu tiền.'
);
GO


/* =========================================================
   51. HÓA ĐƠN
   ========================================================= */

INSERT INTO HoaDon
(
    MaHoaDon,
    DonHangID,
    TongTien,
    GiamGia,
    PhiGiaoHang,
    ThanhTien,
    NgayLap,
    TrangThai
)
VALUES
(
    'HD0001',
    1,
    180000,
    36000,
    0,
    144000,
    '2026-09-26 15:00:00',
    N'Đã thanh toán'
),
(
    'HD0002',
    2,
    120000,
    0,
    0,
    120000,
    '2026-09-24 10:00:00',
    N'Chưa thanh toán'
),
(
    'HD0003',
    3,
    250000,
    50000,
    20000,
    220000,
    '2026-09-24 17:30:00',
    N'Đã thanh toán'
);
GO


/* =========================================================
   52. THANH TOÁN
   ========================================================= */

INSERT INTO ThanhToan
(
    DonHangID,
    SoTien,
    PhuongThuc,
    MaGiaoDich,
    ThoiGian,
    TrangThai,
    GhiChu
)
VALUES
(
    1,
    144000,
    N'Chuyển khoản',
    'BANK-DH0001',
    '2026-09-26 15:05:00',
    N'Thành công',
    N'Khách thanh toán bằng chuyển khoản.'
),
(
    3,
    220000,
    N'Tiền mặt',
    NULL,
    '2026-09-24 17:35:00',
    N'Thành công',
    N'Thu tiền khi giao đồ.'
);
GO


/* =========================================================
   53. LỊCH SỬ THAY ĐỔI HÓA ĐƠN
   ========================================================= */

INSERT INTO LichSuThayDoiHoaDon
(
    HoaDonID,
    TaiKhoanID,
    TruongThayDoi,
    GiaTriCu,
    GiaTriMoi,
    LyDo
)
VALUES
(
    3,
    1,
    'ThanhTien',
    N'240000',
    N'220000',
    N'Điều chỉnh lại dữ liệu theo đơn thực tế.'
);
GO


/* =========================================================
   54. THÔNG BÁO
   ========================================================= */

INSERT INTO ThongBao
(
    TaiKhoanID,
    DonHangID,
    LoaiThongBao,
    TieuDe,
    NoiDung,
    DaDoc
)
VALUES
(
    5,
    1,
    N'Đơn hàng',
    N'Đơn hàng đã hoàn thành',
    N'Đơn hàng DH0001 của bạn đã hoàn thành và được giao.',
    1
),
(
    6,
    2,
    N'Đơn hàng',
    N'Đơn hàng đang được xử lý',
    N'Đơn hàng DH0002 đang được giặt.',
    0
),
(
    7,
    3,
    N'Thanh toán',
    N'Thanh toán thành công',
    N'Bạn đã thanh toán thành công 220.000đ cho đơn DH0003.',
    1
);
GO


/* =========================================================
   55. TIN NHẮN
   ========================================================= */

INSERT INTO TinNhan
(
    NguoiGuiID,
    NguoiNhanID,
    DonHangID,
    NoiDung,
    TrangThai
)
VALUES
(
    3,
    5,
    1,
    N'Đơn hàng của anh/chị đã được giao thành công.',
    N'Đã đọc'
),
(
    5,
    3,
    1,
    N'Cảm ơn cửa hàng.',
    N'Đã đọc'
),
(
    2,
    6,
    2,
    N'Đơn hàng đang được xử lý, dự kiến hoàn thành trong ngày.',
    N'Đã gửi'
);
GO


/* =========================================================
   56. ĐÁNH GIÁ
   ========================================================= */

INSERT INTO DanhGia
(
    DonHangID,
    KhachHangID,
    SoSao,
    BinhLuan,
    TrangThai
)
VALUES
(
    1,
    1,
    5,
    N'Dịch vụ tốt, giao đúng thời gian.',
    N'Hiển thị'
),
(
    3,
    3,
    4,
    N'Đồ sạch, nhân viên nhiệt tình.',
    N'Hiển thị'
);
GO


/* =========================================================
   57. KIỂM TRA SỐ LƯỢNG DỮ LIỆU
   ========================================================= */

SELECT N'KhachHang' AS TenBang, COUNT(*) AS SoLuong FROM KhachHang
UNION ALL
SELECT N'NhanVien', COUNT(*) FROM NhanVien
UNION ALL
SELECT N'TaiKhoan', COUNT(*) FROM TaiKhoan
UNION ALL
SELECT N'VaiTro', COUNT(*) FROM VaiTro
UNION ALL
SELECT N'Quyen', COUNT(*) FROM Quyen
UNION ALL
SELECT N'LoaiDichVu', COUNT(*) FROM LoaiDichVu
UNION ALL
SELECT N'DichVu', COUNT(*) FROM DichVu
UNION ALL
SELECT N'LoaiDoGiat', COUNT(*) FROM LoaiDoGiat
UNION ALL
SELECT N'DonViTinh', COUNT(*) FROM DonViTinh
UNION ALL
SELECT N'BangGia', COUNT(*) FROM BangGia
UNION ALL
SELECT N'KhuyenMai', COUNT(*) FROM KhuyenMai
UNION ALL
SELECT N'Booking', COUNT(*) FROM Booking
UNION ALL
SELECT N'DonHang', COUNT(*) FROM DonHang
UNION ALL
SELECT N'ChiTietDonHang', COUNT(*) FROM ChiTietDonHang
UNION ALL
SELECT N'DiemTichLuy', COUNT(*) FROM DiemTichLuy
UNION ALL
SELECT N'GiaoNhan', COUNT(*) FROM GiaoNhan
UNION ALL
SELECT N'HoaDon', COUNT(*) FROM HoaDon
UNION ALL
SELECT N'ThanhToan', COUNT(*) FROM ThanhToan
UNION ALL
SELECT N'LichSuThayDoiHoaDon', COUNT(*) FROM LichSuThayDoiHoaDon
UNION ALL
SELECT N'ThongBao', COUNT(*) FROM ThongBao
UNION ALL
SELECT N'TinNhan', COUNT(*) FROM TinNhan
UNION ALL
SELECT N'DanhGia', COUNT(*) FROM DanhGia;
GO


/* =========================================================
   58. KIỂM TRA TÀI KHOẢN + VAI TRÒ
   ========================================================= */

SELECT
    tk.TaiKhoanID,
    tk.TenDangNhap,
    vr.TenVaiTro,
    nv.HoTen AS NhanVien,
    kh.HoTen AS KhachHang,
    tk.TrangThai
FROM TaiKhoan tk
INNER JOIN TaiKhoan_VaiTro tkv
    ON tk.TaiKhoanID = tkv.TaiKhoanID
INNER JOIN VaiTro vr
    ON tkv.VaiTroID = vr.VaiTroID
LEFT JOIN NhanVien nv
    ON tk.NhanVienID = nv.NhanVienID
LEFT JOIN KhachHang kh
    ON tk.KhachHangID = kh.KhachHangID
ORDER BY tk.TaiKhoanID;
GO


/* =========================================================
   59. KIỂM TRA BẢNG GIÁ
   ========================================================= */

SELECT
    bg.BangGiaID,
    ld.TenLoaiDoGiat,
    dv.TenDichVu,
    dvt.TenDonViTinh,
    bg.DonGia,
    bg.NgayApDung,
    bg.NgayKetThuc,
    bg.TrangThai
FROM BangGia bg
INNER JOIN DichVu dv
    ON bg.DichVuID = dv.DichVuID
INNER JOIN LoaiDoGiat ld
    ON bg.LoaiDoGiatID = ld.LoaiDoGiatID
INNER JOIN DonViTinh dvt
    ON bg.DonViTinhID = dvt.DonViTinhID
ORDER BY bg.BangGiaID;
GO


/* =========================================================
   60. KIỂM TRA ĐƠN HÀNG
   ========================================================= */

SELECT
    dh.DonHangID,
    dh.MaDonHang,
    kh.HoTen AS KhachHang,
    nv.HoTen AS NhanVien,
    dh.TrangThai,
    dh.TongTien,
    dh.TienGiamDoDiem,
    dh.TienGiamKhuyenMai,
    dh.PhiGiaoHang,
    dh.ThanhTien,
    dh.NgayTao
FROM DonHang dh
INNER JOIN KhachHang kh
    ON dh.KhachHangID = kh.KhachHangID
LEFT JOIN NhanVien nv
    ON dh.NhanVienID = nv.NhanVienID
ORDER BY dh.DonHangID;
GO


/* =========================================================
   61. KIỂM TRA CHI TIẾT ĐƠN HÀNG
   ========================================================= */

SELECT
    dh.MaDonHang,
    kh.HoTen AS KhachHang,
    ld.TenLoaiDoGiat,
    dv.TenDichVu,
    dvt.TenDonViTinh,
    ctdh.SoLuong,
    ctdh.KhoiLuong,
    ctdh.DonGia,
    ctdh.ThanhTien
FROM ChiTietDonHang ctdh
INNER JOIN DonHang dh
    ON ctdh.DonHangID = dh.DonHangID
INNER JOIN KhachHang kh
    ON dh.KhachHangID = kh.KhachHangID
INNER JOIN DichVu dv
    ON ctdh.DichVuID = dv.DichVuID
INNER JOIN LoaiDoGiat ld
    ON ctdh.LoaiDoGiatID = ld.LoaiDoGiatID
INNER JOIN DonViTinh dvt
    ON ctdh.DonViTinhID = dvt.DonViTinhID
ORDER BY dh.DonHangID, ctdh.ChiTietDonHangID;
GO


/* =========================================================
   62. KIỂM TRA GIAO NHẬN
   ========================================================= */

SELECT
    dh.MaDonHang,
    gn.LoaiGiaoNhan,
    gn.HinhThuc,
    nv.HoTen AS NhanVien,
    gn.DiaChi,
    gn.ThoiGianDuKien,
    gn.ThoiGianThucTe,
    gn.PhiGiaoNhan,
    gn.TrangThai
FROM GiaoNhan gn
INNER JOIN DonHang dh
    ON gn.DonHangID = dh.DonHangID
LEFT JOIN NhanVien nv
    ON gn.NhanVienID = nv.NhanVienID
ORDER BY dh.DonHangID, gn.GiaoNhanID;
GO


/* =========================================================
   63. KIỂM TRA HÓA ĐƠN + THANH TOÁN
   ========================================================= */

SELECT
    hd.MaHoaDon,
    dh.MaDonHang,
    kh.HoTen AS KhachHang,
    hd.TongTien,
    hd.GiamGia,
    hd.PhiGiaoHang,
    hd.ThanhTien,
    hd.TrangThai AS TrangThaiHoaDon,
    tt.SoTien AS SoTienThanhToan,
    tt.PhuongThuc,
    tt.TrangThai AS TrangThaiThanhToan
FROM HoaDon hd
INNER JOIN DonHang dh
    ON hd.DonHangID = dh.DonHangID
INNER JOIN KhachHang kh
    ON dh.KhachHangID = kh.KhachHangID
LEFT JOIN ThanhToan tt
    ON dh.DonHangID = tt.DonHangID
ORDER BY hd.HoaDonID;
GO


/* =========================================================
   64. KIỂM TRA BOOKING -> ĐƠN HÀNG
   ========================================================= */

SELECT
    b.MaBooking,
    kh.HoTen AS KhachHang,
    b.HinhThucNhanDo,
    b.NgayHen,
    b.GioHen,
    b.TrangThai AS TrangThaiBooking,
    dh.MaDonHang,
    dh.TrangThai AS TrangThaiDonHang
FROM Booking b
INNER JOIN KhachHang kh
    ON b.KhachHangID = kh.KhachHangID
LEFT JOIN DonHang dh
    ON b.BookingID = dh.BookingID
ORDER BY b.BookingID;
GO


/* =========================================================
   KẾT THÚC SCRIPT
   ========================================================= */

PRINT N'=========================================================';
PRINT N'ĐÃ TẠO DATABASE QLGiatUi VÀ INSERT DỮ LIỆU MẪU THÀNH CÔNG.';
PRINT N'Tài khoản mẫu: owner / 123456';
PRINT N'=========================================================';
GO