-- Read-only schema snapshot from PostgreSQL catalog metadata.
-- Source schema: public; captured 2026-09-30T09:44:54+00:00
-- DDL text only: this file was generated locally; no DDL was executed.

CREATE TABLE "BangGia" (
    "BangGiaID" integer DEFAULT nextval('"BangGia_BangGiaID_seq"'::regclass) NOT NULL,
    "DichVuID" integer NOT NULL,
    "LoaiDoGiatID" integer NOT NULL,
    "DonViTinhID" integer NOT NULL,
    "DonGia" numeric(18,2) NOT NULL,
    "NgayApDung" date NOT NULL,
    "NgayKetThuc" date,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "BangGia_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID"),
    CONSTRAINT "BangGia_DonGia_check" CHECK (("DonGia" >= (0)::numeric)),
    CONSTRAINT "BangGia_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID"),
    CONSTRAINT "BangGia_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID"),
    CONSTRAINT "BangGia_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Hết hiệu lực'::character varying, 'Tạm ngưng'::character varying])::text[]))),
    CONSTRAINT "BangGia_pkey" PRIMARY KEY ("BangGiaID"),
    CONSTRAINT "CK_BangGia_Ngay" CHECK ((("NgayKetThuc" IS NULL) OR ("NgayKetThuc" >= "NgayApDung")))
);

CREATE TABLE "Booking" (
    "BookingID" integer DEFAULT nextval('"Booking_BookingID_seq"'::regclass) NOT NULL,
    "MaBooking" character varying(30) NOT NULL,
    "KhachHangID" integer NOT NULL,
    "HinhThucNhanDo" character varying(30) NOT NULL,
    "DiaChiNhan" character varying(255),
    "NgayHen" date NOT NULL,
    "GioHen" time without time zone NOT NULL,
    "GhiChu" character varying(500),
    "TrangThai" character varying(30) DEFAULT 'ChoTiepNhan'::character varying NOT NULL,
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "NgayCapNhat" timestamp without time zone,
    "IdempotencyKey" uuid,
    "DichVuID" integer,
    "LoaiDoGiatID" integer,
    "DonViTinhID" integer,
    "SoLuong" numeric(10,2),
    "KhoiLuong" numeric(10,2),
    "DonGia" numeric(18,2),
    "ThanhTien" numeric(18,2),
    "NhanVienID" integer,
    CONSTRAINT "Booking_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID"),
    CONSTRAINT "Booking_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID"),
    CONSTRAINT "Booking_HinhThucNhanDo_check" CHECK ((("HinhThucNhanDo")::text = ANY ((ARRAY['Tại cửa hàng'::character varying, 'Tại nhà'::character varying])::text[]))),
    CONSTRAINT "Booking_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID"),
    CONSTRAINT "Booking_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID"),
    CONSTRAINT "Booking_MaBooking_key" UNIQUE ("MaBooking"),
    CONSTRAINT "Booking_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID") ON DELETE SET NULL,
    CONSTRAINT "Booking_pkey" PRIMARY KEY ("BookingID"),
    CONSTRAINT "booking_service_snapshot_check" CHECK (((("DichVuID" IS NULL) AND ("LoaiDoGiatID" IS NULL) AND ("DonViTinhID" IS NULL) AND ("SoLuong" IS NULL) AND ("KhoiLuong" IS NULL) AND ("DonGia" IS NULL) AND ("ThanhTien" IS NULL)) OR (("DichVuID" IS NOT NULL) AND ("LoaiDoGiatID" IS NOT NULL) AND ("DonViTinhID" IS NOT NULL) AND ("DonGia" IS NOT NULL) AND ("ThanhTien" IS NOT NULL) AND ("DonGia" >= (0)::numeric) AND ("ThanhTien" >= (0)::numeric) AND ((("SoLuong" > (0)::numeric) AND ("KhoiLuong" IS NULL)) OR (("KhoiLuong" > (0)::numeric) AND ("SoLuong" IS NULL)))))),
    CONSTRAINT "booking_trangthai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['ChoTiepNhan'::character varying, 'DaXacNhan'::character varying, 'DaHuy'::character varying, 'HoanThanh'::character varying])::text[])))
);

CREATE TABLE "ChiTietDonHang" (
    "ChiTietDonHangID" integer DEFAULT nextval('"ChiTietDonHang_ChiTietDonHangID_seq"'::regclass) NOT NULL,
    "DonHangID" integer NOT NULL,
    "DichVuID" integer NOT NULL,
    "LoaiDoGiatID" integer NOT NULL,
    "DonViTinhID" integer NOT NULL,
    "SoLuong" numeric(10,2),
    "KhoiLuong" numeric(10,2),
    "DonGia" numeric(18,2) NOT NULL,
    "ThanhTien" numeric(18,2) NOT NULL,
    "GhiChu" character varying(500),
    CONSTRAINT "CK_CTDH_SoLuongKhoiLuong" CHECK (((("SoLuong" IS NOT NULL) AND ("SoLuong" > (0)::numeric)) OR (("KhoiLuong" IS NOT NULL) AND ("KhoiLuong" > (0)::numeric)))),
    CONSTRAINT "ChiTietDonHang_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID"),
    CONSTRAINT "ChiTietDonHang_DonGia_check" CHECK (("DonGia" >= (0)::numeric)),
    CONSTRAINT "ChiTietDonHang_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "ChiTietDonHang_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID"),
    CONSTRAINT "ChiTietDonHang_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID"),
    CONSTRAINT "ChiTietDonHang_ThanhTien_check" CHECK (("ThanhTien" >= (0)::numeric)),
    CONSTRAINT "ChiTietDonHang_pkey" PRIMARY KEY ("ChiTietDonHangID")
);

CREATE TABLE "DanhGia" (
    "DanhGiaID" integer DEFAULT nextval('"DanhGia_DanhGiaID_seq"'::regclass) NOT NULL,
    "DonHangID" integer NOT NULL,
    "KhachHangID" integer NOT NULL,
    "SoSao" integer NOT NULL,
    "BinhLuan" character varying(1000),
    "NgayDanhGia" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Hiển thị'::character varying NOT NULL,
    CONSTRAINT "DanhGia_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "DanhGia_DonHangID_key" UNIQUE ("DonHangID"),
    CONSTRAINT "DanhGia_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID"),
    CONSTRAINT "DanhGia_SoSao_check" CHECK ((("SoSao" >= 1) AND ("SoSao" <= 5))),
    CONSTRAINT "DanhGia_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hiển thị'::character varying, 'Ẩn'::character varying])::text[]))),
    CONSTRAINT "DanhGia_pkey" PRIMARY KEY ("DanhGiaID")
);

CREATE TABLE "DichVu" (
    "DichVuID" integer DEFAULT nextval('"DichVu_DichVuID_seq"'::regclass) NOT NULL,
    "LoaiDichVuID" integer NOT NULL,
    "TenDichVu" character varying(150) NOT NULL,
    "MoTa" character varying(500),
    "ThoiGianDuKien" integer,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT "DichVu_LoaiDichVuID_fkey" FOREIGN KEY ("LoaiDichVuID") REFERENCES "LoaiDichVu"("LoaiDichVuID"),
    CONSTRAINT "DichVu_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying])::text[]))),
    CONSTRAINT "DichVu_pkey" PRIMARY KEY ("DichVuID")
);

CREATE TABLE "DiemTichLuy" (
    "DiemTichLuyID" integer DEFAULT nextval('"DiemTichLuy_DiemTichLuyID_seq"'::regclass) NOT NULL,
    "KhachHangID" integer NOT NULL,
    "DiemHienTai" integer DEFAULT 0 NOT NULL,
    "NgayCapNhat" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT "DiemTichLuy_DiemHienTai_check" CHECK (("DiemHienTai" >= 0)),
    CONSTRAINT "DiemTichLuy_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID"),
    CONSTRAINT "DiemTichLuy_KhachHangID_key" UNIQUE ("KhachHangID"),
    CONSTRAINT "DiemTichLuy_pkey" PRIMARY KEY ("DiemTichLuyID")
);

CREATE TABLE "DonHang" (
    "DonHangID" integer DEFAULT nextval('"DonHang_DonHangID_seq"'::regclass) NOT NULL,
    "MaDonHang" character varying(30) NOT NULL,
    "BookingID" integer,
    "KhachHangID" integer NOT NULL,
    "NhanVienID" integer,
    "TrangThai" character varying(30) DEFAULT 'Chờ tiếp nhận'::character varying NOT NULL,
    "TongTien" numeric(18,2) DEFAULT 0 NOT NULL,
    "DiemSuDung" integer DEFAULT 0 NOT NULL,
    "TienGiamDoDiem" numeric(18,2) DEFAULT 0 NOT NULL,
    "KhuyenMaiID" integer,
    "TienGiamKhuyenMai" numeric(18,2) DEFAULT 0 NOT NULL,
    "PhiGiaoHang" numeric(18,2) DEFAULT 0 NOT NULL,
    "ThanhTien" numeric(18,2) DEFAULT 0 NOT NULL,
    "GhiChu" character varying(500),
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "NgayCapNhat" timestamp without time zone,
    "IdempotencyKey" uuid,
    CONSTRAINT "DonHang_BookingID_fkey" FOREIGN KEY ("BookingID") REFERENCES "Booking"("BookingID"),
    CONSTRAINT "DonHang_DiemSuDung_check" CHECK (("DiemSuDung" >= 0)),
    CONSTRAINT "DonHang_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID"),
    CONSTRAINT "DonHang_KhuyenMaiID_fkey" FOREIGN KEY ("KhuyenMaiID") REFERENCES "KhuyenMai"("KhuyenMaiID"),
    CONSTRAINT "DonHang_MaDonHang_key" UNIQUE ("MaDonHang"),
    CONSTRAINT "DonHang_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID"),
    CONSTRAINT "DonHang_PhiGiaoHang_check" CHECK (("PhiGiaoHang" >= (0)::numeric)),
    CONSTRAINT "DonHang_ThanhTien_check" CHECK (("ThanhTien" >= (0)::numeric)),
    CONSTRAINT "DonHang_TienGiamDoDiem_check" CHECK (("TienGiamDoDiem" >= (0)::numeric)),
    CONSTRAINT "DonHang_TienGiamKhuyenMai_check" CHECK (("TienGiamKhuyenMai" >= (0)::numeric)),
    CONSTRAINT "DonHang_TongTien_check" CHECK (("TongTien" >= (0)::numeric)),
    CONSTRAINT "DonHang_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Chờ tiếp nhận'::character varying, 'Đã tiếp nhận'::character varying, 'Đang giặt'::character varying, 'Hoàn thành giặt'::character varying, 'Đang giao'::character varying, 'Đã giao'::character varying, 'Đã thanh toán'::character varying, 'Đã hủy'::character varying])::text[]))),
    CONSTRAINT "DonHang_pkey" PRIMARY KEY ("DonHangID")
);

CREATE TABLE "DonViTinh" (
    "DonViTinhID" integer DEFAULT nextval('"DonViTinh_DonViTinhID_seq"'::regclass) NOT NULL,
    "TenDonViTinh" character varying(50) NOT NULL,
    "KyHieu" character varying(20),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "DonViTinh_TenDonViTinh_key" UNIQUE ("TenDonViTinh"),
    CONSTRAINT "DonViTinh_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying])::text[]))),
    CONSTRAINT "DonViTinh_pkey" PRIMARY KEY ("DonViTinhID")
);

CREATE TABLE "GiaoNhan" (
    "GiaoNhanID" integer DEFAULT nextval('"GiaoNhan_GiaoNhanID_seq"'::regclass) NOT NULL,
    "DonHangID" integer NOT NULL,
    "NhanVienID" integer,
    "LoaiGiaoNhan" character varying(30) NOT NULL,
    "HinhThuc" character varying(30) NOT NULL,
    "DiaChi" character varying(255),
    "ThoiGianDuKien" timestamp without time zone,
    "ThoiGianThucTe" timestamp without time zone,
    "PhiGiaoNhan" numeric(18,2) DEFAULT 0 NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Chờ thực hiện'::character varying NOT NULL,
    "GhiChu" character varying(500),
    CONSTRAINT "GiaoNhan_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "GiaoNhan_HinhThuc_check" CHECK ((("HinhThuc")::text = ANY ((ARRAY['Tại cửa hàng'::character varying, 'Tại nhà'::character varying])::text[]))),
    CONSTRAINT "GiaoNhan_LoaiGiaoNhan_check" CHECK ((("LoaiGiaoNhan")::text = ANY ((ARRAY['NHAN_DO'::character varying, 'GIAO_DO'::character varying])::text[]))),
    CONSTRAINT "GiaoNhan_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID"),
    CONSTRAINT "GiaoNhan_PhiGiaoNhan_check" CHECK (("PhiGiaoNhan" >= (0)::numeric)),
    CONSTRAINT "GiaoNhan_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Chờ thực hiện'::character varying, 'Đang thực hiện'::character varying, 'Hoàn thành'::character varying, 'Đã hủy'::character varying])::text[]))),
    CONSTRAINT "GiaoNhan_pkey" PRIMARY KEY ("GiaoNhanID")
);

CREATE TABLE "HoaDon" (
    "HoaDonID" integer DEFAULT nextval('"HoaDon_HoaDonID_seq"'::regclass) NOT NULL,
    "MaHoaDon" character varying(30) NOT NULL,
    "DonHangID" integer NOT NULL,
    "TongTien" numeric(18,2) NOT NULL,
    "GiamGia" numeric(18,2) DEFAULT 0 NOT NULL,
    "PhiGiaoHang" numeric(18,2) DEFAULT 0 NOT NULL,
    "ThanhTien" numeric(18,2) NOT NULL,
    "NgayLap" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Chưa thanh toán'::character varying NOT NULL,
    CONSTRAINT "HoaDon_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "HoaDon_DonHangID_key" UNIQUE ("DonHangID"),
    CONSTRAINT "HoaDon_GiamGia_check" CHECK (("GiamGia" >= (0)::numeric)),
    CONSTRAINT "HoaDon_MaHoaDon_key" UNIQUE ("MaHoaDon"),
    CONSTRAINT "HoaDon_PhiGiaoHang_check" CHECK (("PhiGiaoHang" >= (0)::numeric)),
    CONSTRAINT "HoaDon_ThanhTien_check" CHECK (("ThanhTien" >= (0)::numeric)),
    CONSTRAINT "HoaDon_TongTien_check" CHECK (("TongTien" >= (0)::numeric)),
    CONSTRAINT "HoaDon_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Chưa thanh toán'::character varying, 'Đã thanh toán'::character varying, 'Đã hủy'::character varying])::text[]))),
    CONSTRAINT "HoaDon_pkey" PRIMARY KEY ("HoaDonID")
);

CREATE TABLE "KhachHang" (
    "KhachHangID" integer DEFAULT nextval('"KhachHang_KhachHangID_seq"'::regclass) NOT NULL,
    "HoTen" character varying(100) NOT NULL,
    "SoDienThoai" character varying(15),
    "Email" character varying(150),
    "DiaChi" character varying(255),
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "KhachHang_SoDienThoai_key" UNIQUE ("SoDienThoai"),
    CONSTRAINT "KhachHang_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Khóa'::character varying, 'Ngừng hoạt động'::character varying])::text[]))),
    CONSTRAINT "KhachHang_pkey" PRIMARY KEY ("KhachHangID")
);

CREATE TABLE "KhuyenMai" (
    "KhuyenMaiID" integer DEFAULT nextval('"KhuyenMai_KhuyenMaiID_seq"'::regclass) NOT NULL,
    "MaKhuyenMai" character varying(50) NOT NULL,
    "TenKhuyenMai" character varying(150) NOT NULL,
    "LoaiKhuyenMai" character varying(30) NOT NULL,
    "GiaTriGiam" numeric(18,2) NOT NULL,
    "GiaTriDonToiThieu" numeric(18,2),
    "MucGiamToiDa" numeric(18,2),
    "SoLuongSuDung" integer,
    "DieuKienApDung" character varying(500),
    "NgayBatDau" date NOT NULL,
    "NgayKetThuc" date NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "CK_KhuyenMai_Ngay" CHECK (("NgayKetThuc" >= "NgayBatDau")),
    CONSTRAINT "KhuyenMai_GiaTriGiam_check" CHECK (("GiaTriGiam" >= (0)::numeric)),
    CONSTRAINT "KhuyenMai_LoaiKhuyenMai_check" CHECK ((("LoaiKhuyenMai")::text = ANY ((ARRAY['Phần trăm'::character varying, 'Tiền mặt'::character varying])::text[]))),
    CONSTRAINT "KhuyenMai_MaKhuyenMai_key" UNIQUE ("MaKhuyenMai"),
    CONSTRAINT "KhuyenMai_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying, 'Hết hạn'::character varying])::text[]))),
    CONSTRAINT "KhuyenMai_pkey" PRIMARY KEY ("KhuyenMaiID")
);

CREATE TABLE "LichSuThayDoiHoaDon" (
    "LichSuID" integer DEFAULT nextval('"LichSuThayDoiHoaDon_LichSuID_seq"'::regclass) NOT NULL,
    "HoaDonID" integer NOT NULL,
    "TaiKhoanID" integer NOT NULL,
    "ThoiGian" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TruongThayDoi" character varying(100) NOT NULL,
    "GiaTriCu" character varying(500),
    "GiaTriMoi" character varying(500),
    "LyDo" character varying(500),
    CONSTRAINT "LichSuThayDoiHoaDon_HoaDonID_fkey" FOREIGN KEY ("HoaDonID") REFERENCES "HoaDon"("HoaDonID"),
    CONSTRAINT "LichSuThayDoiHoaDon_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID"),
    CONSTRAINT "LichSuThayDoiHoaDon_pkey" PRIMARY KEY ("LichSuID")
);

CREATE TABLE "LoaiDichVu" (
    "LoaiDichVuID" integer DEFAULT nextval('"LoaiDichVu_LoaiDichVuID_seq"'::regclass) NOT NULL,
    "TenLoaiDichVu" character varying(100) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "LoaiDichVu_TenLoaiDichVu_key" UNIQUE ("TenLoaiDichVu"),
    CONSTRAINT "LoaiDichVu_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying])::text[]))),
    CONSTRAINT "LoaiDichVu_pkey" PRIMARY KEY ("LoaiDichVuID")
);

CREATE TABLE "LoaiDoGiat" (
    "LoaiDoGiatID" integer DEFAULT nextval('"LoaiDoGiat_LoaiDoGiatID_seq"'::regclass) NOT NULL,
    "TenLoaiDoGiat" character varying(150) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "LoaiDoGiat_TenLoaiDoGiat_key" UNIQUE ("TenLoaiDoGiat"),
    CONSTRAINT "LoaiDoGiat_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying])::text[]))),
    CONSTRAINT "LoaiDoGiat_pkey" PRIMARY KEY ("LoaiDoGiatID")
);

CREATE TABLE "NhanVien" (
    "NhanVienID" integer DEFAULT nextval('"NhanVien_NhanVienID_seq"'::regclass) NOT NULL,
    "HoTen" character varying(100) NOT NULL,
    "SoDienThoai" character varying(15) NOT NULL,
    "Email" character varying(150),
    "DiaChi" character varying(255),
    "ChucDanh" character varying(100),
    "NgayVaoLam" date,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "NhanVien_SoDienThoai_key" UNIQUE ("SoDienThoai"),
    CONSTRAINT "NhanVien_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Khóa'::character varying, 'Ngừng hoạt động'::character varying])::text[]))),
    CONSTRAINT "NhanVien_pkey" PRIMARY KEY ("NhanVienID")
);

CREATE TABLE "Quyen" (
    "QuyenID" integer DEFAULT nextval('"Quyen_QuyenID_seq"'::regclass) NOT NULL,
    "MaQuyen" character varying(100) NOT NULL,
    "TenQuyen" character varying(150) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "Quyen_MaQuyen_key" UNIQUE ("MaQuyen"),
    CONSTRAINT "Quyen_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Ngừng hoạt động'::character varying])::text[]))),
    CONSTRAINT "Quyen_pkey" PRIMARY KEY ("QuyenID")
);

CREATE TABLE "TaiKhoan" (
    "TaiKhoanID" integer DEFAULT nextval('"TaiKhoan_TaiKhoanID_seq"'::regclass) NOT NULL,
    "TenDangNhap" character varying(100) NOT NULL,
    "MatKhau" character varying(255),
    "Email" character varying(150),
    "SoDienThoai" character varying(15),
    "NhanVienID" integer,
    "KhachHangID" integer,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "UserAuthId" uuid,
    CONSTRAINT "CK_TaiKhoan_DoiTuong" CHECK (((("NhanVienID" IS NOT NULL) AND ("KhachHangID" IS NULL)) OR (("NhanVienID" IS NULL) AND ("KhachHangID" IS NOT NULL)))),
    CONSTRAINT "TaiKhoan_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID"),
    CONSTRAINT "TaiKhoan_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID"),
    CONSTRAINT "TaiKhoan_TenDangNhap_key" UNIQUE ("TenDangNhap"),
    CONSTRAINT "TaiKhoan_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Khóa'::character varying, 'Ngừng hoạt động'::character varying])::text[]))),
    CONSTRAINT "TaiKhoan_pkey" PRIMARY KEY ("TaiKhoanID")
);

CREATE TABLE "TaiKhoan_VaiTro" (
    "TaiKhoanID" integer NOT NULL,
    "VaiTroID" integer NOT NULL,
    CONSTRAINT "TaiKhoan_VaiTro_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID"),
    CONSTRAINT "TaiKhoan_VaiTro_VaiTroID_fkey" FOREIGN KEY ("VaiTroID") REFERENCES "VaiTro"("VaiTroID"),
    CONSTRAINT "TaiKhoan_VaiTro_pkey" PRIMARY KEY ("TaiKhoanID", "VaiTroID")
);

CREATE TABLE "ThanhToan" (
    "ThanhToanID" integer DEFAULT nextval('"ThanhToan_ThanhToanID_seq"'::regclass) NOT NULL,
    "DonHangID" integer NOT NULL,
    "SoTien" numeric(18,2) NOT NULL,
    "PhuongThuc" character varying(30) NOT NULL,
    "MaGiaoDich" character varying(100),
    "ThoiGian" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Chờ thanh toán'::character varying NOT NULL,
    "GhiChu" character varying(500),
    CONSTRAINT "ThanhToan_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "ThanhToan_PhuongThuc_check" CHECK ((("PhuongThuc")::text = ANY ((ARRAY['Tiền mặt'::character varying, 'Chuyển khoản'::character varying])::text[]))),
    CONSTRAINT "ThanhToan_SoTien_check" CHECK (("SoTien" > (0)::numeric)),
    CONSTRAINT "ThanhToan_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Chờ thanh toán'::character varying, 'Thành công'::character varying, 'Thất bại'::character varying, 'Đã hoàn tiền'::character varying])::text[]))),
    CONSTRAINT "ThanhToan_pkey" PRIMARY KEY ("ThanhToanID")
);

CREATE TABLE "ThongBao" (
    "ThongBaoID" integer DEFAULT nextval('"ThongBao_ThongBaoID_seq"'::regclass) NOT NULL,
    "TaiKhoanID" integer NOT NULL,
    "DonHangID" integer,
    "LoaiThongBao" character varying(50),
    "TieuDe" character varying(200) NOT NULL,
    "NoiDung" character varying(1000) NOT NULL,
    "ThoiGianGui" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "DaDoc" boolean DEFAULT false NOT NULL,
    CONSTRAINT "ThongBao_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "ThongBao_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID"),
    CONSTRAINT "ThongBao_pkey" PRIMARY KEY ("ThongBaoID")
);

CREATE TABLE "TinNhan" (
    "TinNhanID" integer DEFAULT nextval('"TinNhan_TinNhanID_seq"'::regclass) NOT NULL,
    "NguoiGuiID" integer NOT NULL,
    "NguoiNhanID" integer NOT NULL,
    "DonHangID" integer,
    "NoiDung" character varying(1000) NOT NULL,
    "ThoiGianGui" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Đã gửi'::character varying NOT NULL,
    CONSTRAINT "TinNhan_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID"),
    CONSTRAINT "TinNhan_NguoiGuiID_fkey" FOREIGN KEY ("NguoiGuiID") REFERENCES "TaiKhoan"("TaiKhoanID"),
    CONSTRAINT "TinNhan_NguoiNhanID_fkey" FOREIGN KEY ("NguoiNhanID") REFERENCES "TaiKhoan"("TaiKhoanID"),
    CONSTRAINT "TinNhan_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Đã gửi'::character varying, 'Đã nhận'::character varying, 'Đã đọc'::character varying])::text[]))),
    CONSTRAINT "TinNhan_pkey" PRIMARY KEY ("TinNhanID")
);

CREATE TABLE "VaiTro" (
    "VaiTroID" integer DEFAULT nextval('"VaiTro_VaiTroID_seq"'::regclass) NOT NULL,
    "TenVaiTro" character varying(100) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    CONSTRAINT "VaiTro_TenVaiTro_key" UNIQUE ("TenVaiTro"),
    CONSTRAINT "VaiTro_TrangThai_check" CHECK ((("TrangThai")::text = ANY ((ARRAY['Hoạt động'::character varying, 'Ngừng hoạt động'::character varying])::text[]))),
    CONSTRAINT "VaiTro_pkey" PRIMARY KEY ("VaiTroID")
);

CREATE TABLE "VaiTro_Quyen" (
    "VaiTroID" integer NOT NULL,
    "QuyenID" integer NOT NULL,
    CONSTRAINT "VaiTro_Quyen_QuyenID_fkey" FOREIGN KEY ("QuyenID") REFERENCES "Quyen"("QuyenID"),
    CONSTRAINT "VaiTro_Quyen_VaiTroID_fkey" FOREIGN KEY ("VaiTroID") REFERENCES "VaiTro"("VaiTroID"),
    CONSTRAINT "VaiTro_Quyen_pkey" PRIMARY KEY ("VaiTroID", "QuyenID")
);

CREATE TABLE "bookings" (
    "id" bigint DEFAULT nextval('bookings_id_seq'::regclass) NOT NULL,
    "customer_id" bigint NOT NULL,
    "service_id" bigint NOT NULL,
    "garment_type" character varying(255) NOT NULL,
    "quantity" integer DEFAULT 1 NOT NULL,
    "delivery_method" character varying(255) DEFAULT 'pickup'::character varying NOT NULL,
    "address" text,
    "pickup_date" date NOT NULL,
    "pickup_time" time(0) without time zone NOT NULL,
    "notes" text,
    "status" character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    "created_at" timestamp(0) without time zone,
    "updated_at" timestamp(0) without time zone,
    "deleted_at" timestamp(0) without time zone,
    CONSTRAINT "bookings_pkey" PRIMARY KEY (id)
);

CREATE TABLE "donhang_trangthai" (
    "donhang_trangthaiid" bigint GENERATED ALWAYS AS IDENTITY NOT NULL,
    "donhangid" bigint NOT NULL,
    "taikhoanid" bigint,
    "trangthaicu" character varying(30),
    "trangthaimoi" character varying(30) NOT NULL,
    "lydo" character varying(500),
    "thoigian" timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT "donhang_trangthai_donhangid_fkey" FOREIGN KEY (donhangid) REFERENCES "DonHang"("DonHangID") ON DELETE CASCADE,
    CONSTRAINT "donhang_trangthai_pkey" PRIMARY KEY (donhang_trangthaiid),
    CONSTRAINT "donhang_trangthai_taikhoanid_fkey" FOREIGN KEY (taikhoanid) REFERENCES "TaiKhoan"("TaiKhoanID") ON DELETE SET NULL
);

CREATE TABLE "khachhang_diachi" (
    "diachiid" bigint GENERATED ALWAYS AS IDENTITY NOT NULL,
    "khachhangid" bigint NOT NULL,
    "tennguoinhan" character varying(100) NOT NULL,
    "sodienthoai" character varying(15) NOT NULL,
    "diachi" character varying(500) NOT NULL,
    "ghichu" character varying(500),
    "macdinh" boolean DEFAULT false NOT NULL,
    "ngaytao" timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT "khachhang_diachi_diachi_check" CHECK ((length(btrim((diachi)::text)) > 0)),
    CONSTRAINT "khachhang_diachi_khachhangid_fkey" FOREIGN KEY (khachhangid) REFERENCES "KhachHang"("KhachHangID") ON DELETE CASCADE,
    CONSTRAINT "khachhang_diachi_pkey" PRIMARY KEY (diachiid),
    CONSTRAINT "khachhang_diachi_sodienthoai_check" CHECK ((length(btrim((sodienthoai)::text)) >= 8))
);

CREATE TABLE "sessions" (
    "id" character varying(255) NOT NULL,
    "user_id" bigint,
    "ip_address" character varying(45),
    "user_agent" text,
    "payload" text NOT NULL,
    "last_activity" integer NOT NULL,
    CONSTRAINT "sessions_pkey" PRIMARY KEY (id)
);

CREATE VIEW "banggia" AS
 SELECT "BangGiaID" AS banggiaid,
    "DichVuID" AS dichvuid,
    "LoaiDoGiatID" AS loaidogiatid,
    "DonViTinhID" AS donvitinhid,
    "DonGia" AS dongia,
    "NgayApDung" AS ngayapdung,
    "NgayKetThuc" AS ngayketthuc,
    "TrangThai" AS trangthai
   FROM "BangGia";;

CREATE VIEW "booking" AS
 SELECT "BookingID" AS bookingid,
    "MaBooking" AS mabooking,
    "KhachHangID" AS khachhangid,
    "HinhThucNhanDo" AS hinhthucnhando,
    "DiaChiNhan" AS diachinhan,
    "NgayHen" AS ngayhen,
    "GioHen" AS giohen,
    "GhiChu" AS ghichu,
    "TrangThai" AS trangthai,
    "NgayTao" AS ngaytao,
    "NgayCapNhat" AS ngaycapnhat,
    "IdempotencyKey" AS idempotency_key,
    "DichVuID" AS dichvuid,
    "LoaiDoGiatID" AS loaidogiatid,
    "DonViTinhID" AS donvitinhid,
    "SoLuong" AS soluong,
    "KhoiLuong" AS khoiluong,
    "DonGia" AS dongia,
    "ThanhTien" AS thanhtien
   FROM "Booking";;

CREATE VIEW "chitietdonhang" AS
 SELECT "ChiTietDonHangID" AS chitietdonhangid,
    "DonHangID" AS donhangid,
    "DichVuID" AS dichvuid,
    "LoaiDoGiatID" AS loaidogiatid,
    "DonViTinhID" AS donvitinhid,
    "SoLuong" AS soluong,
    "KhoiLuong" AS khoiluong,
    "DonGia" AS dongia,
    "ThanhTien" AS thanhtien,
    "GhiChu" AS ghichu
   FROM "ChiTietDonHang";;

CREATE VIEW "danhgia" AS
 SELECT "DanhGiaID" AS danhgiaid,
    "DonHangID" AS donhangid,
    "KhachHangID" AS khachhangid,
    "SoSao" AS sosao,
    "BinhLuan" AS binhluan,
    "NgayDanhGia" AS ngaydanhgia,
    "TrangThai" AS trangthai
   FROM "DanhGia";;

CREATE VIEW "dichvu" AS
 SELECT "DichVuID" AS dichvuid,
    "LoaiDichVuID" AS loaidichvuid,
    "TenDichVu" AS tendichvu,
    "MoTa" AS mota,
    "ThoiGianDuKien" AS thoigiandukien,
    "TrangThai" AS trangthai,
    "NgayTao" AS ngaytao
   FROM "DichVu";;

CREATE VIEW "diemtichluy" AS
 SELECT "DiemTichLuyID" AS diemtichluyid,
    "KhachHangID" AS khachhangid,
    "DiemHienTai" AS diemhientai,
    "NgayCapNhat" AS ngaycapnhat
   FROM "DiemTichLuy";;

CREATE VIEW "donhang" AS
 SELECT "DonHangID" AS donhangid,
    "MaDonHang" AS madonhang,
    "BookingID" AS bookingid,
    "KhachHangID" AS khachhangid,
    "NhanVienID" AS nhanvienid,
    "TrangThai" AS trangthai,
    "TongTien" AS tongtien,
    "DiemSuDung" AS diemsudung,
    "TienGiamDoDiem" AS tiengiamdodiem,
    "KhuyenMaiID" AS khuyenmaiid,
    "TienGiamKhuyenMai" AS tiengiamkhuyenmai,
    "PhiGiaoHang" AS phigiaohang,
    "ThanhTien" AS thanhtien,
    "GhiChu" AS ghichu,
    "NgayTao" AS ngaytao,
    "NgayCapNhat" AS ngaycapnhat,
    "IdempotencyKey" AS idempotency_key
   FROM "DonHang";;

CREATE VIEW "donvitinh" AS
 SELECT "DonViTinhID" AS donvitinhid,
    "TenDonViTinh" AS tendonvitinh,
    "KyHieu" AS kyhieu,
    "TrangThai" AS trangthai
   FROM "DonViTinh";;

CREATE VIEW "giaonhan" AS
 SELECT "GiaoNhanID" AS giaonhanid,
    "DonHangID" AS donhangid,
    "NhanVienID" AS nhanvienid,
    "LoaiGiaoNhan" AS loaigiaonhan,
    "HinhThuc" AS hinhthuc,
    "DiaChi" AS diachi,
    "ThoiGianDuKien" AS thoigiandukien,
    "ThoiGianThucTe" AS thoigianthucte,
    "PhiGiaoNhan" AS phigiaonhan,
    "TrangThai" AS trangthai,
    "GhiChu" AS ghichu
   FROM "GiaoNhan";;

CREATE VIEW "hoadon" AS
 SELECT "HoaDonID" AS hoadonid,
    "MaHoaDon" AS mahoadon,
    "DonHangID" AS donhangid,
    "TongTien" AS tongtien,
    "GiamGia" AS giamgia,
    "PhiGiaoHang" AS phigiaohang,
    "ThanhTien" AS thanhtien,
    "NgayLap" AS ngaylap,
    "TrangThai" AS trangthai
   FROM "HoaDon";;

CREATE VIEW "khachhang" AS
 SELECT "KhachHangID" AS khachhangid,
    "HoTen" AS hoten,
    "SoDienThoai" AS sodienthoai,
    "Email" AS email,
    "DiaChi" AS diachi,
    "NgayTao" AS ngaytao,
    "TrangThai" AS trangthai
   FROM "KhachHang";;

CREATE VIEW "khuyenmai" AS
 SELECT "KhuyenMaiID" AS khuyenmaiid,
    "MaKhuyenMai" AS makhuyenmai,
    "TenKhuyenMai" AS tenkhuyenmai,
    "LoaiKhuyenMai" AS loaikhuyenmai,
    "GiaTriGiam" AS giatrigiam,
    "GiaTriDonToiThieu" AS giatridontoithieu,
    "MucGiamToiDa" AS mucgiamtoida,
    "SoLuongSuDung" AS soluongsudung,
    "DieuKienApDung" AS dieukienapdung,
    "NgayBatDau" AS ngaybatdau,
    "NgayKetThuc" AS ngayketthuc,
    "TrangThai" AS trangthai
   FROM "KhuyenMai";;

CREATE VIEW "lichsuthaydoihoadon" AS
 SELECT "LichSuID" AS lichsuid,
    "HoaDonID" AS hoadonid,
    "TaiKhoanID" AS taikhoanid,
    "ThoiGian" AS thoigian,
    "TruongThayDoi" AS truongthaydoi,
    "GiaTriCu" AS giatricu,
    "GiaTriMoi" AS giatrimoi,
    "LyDo" AS lydo
   FROM "LichSuThayDoiHoaDon";;

CREATE VIEW "loaidichvu" AS
 SELECT "LoaiDichVuID" AS loaidichvuid,
    "TenLoaiDichVu" AS tenloaidichvu,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "LoaiDichVu";;

CREATE VIEW "loaidogiat" AS
 SELECT "LoaiDoGiatID" AS loaidogiatid,
    "TenLoaiDoGiat" AS tenloaidogiat,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "LoaiDoGiat";;

CREATE VIEW "nhanvien" AS
 SELECT "NhanVienID" AS nhanvienid,
    "HoTen" AS hoten,
    "SoDienThoai" AS sodienthoai,
    "Email" AS email,
    "DiaChi" AS diachi,
    "ChucDanh" AS chucdanh,
    "NgayVaoLam" AS ngayvaolam,
    "TrangThai" AS trangthai
   FROM "NhanVien";;

CREATE VIEW "quyen" AS
 SELECT "QuyenID" AS quyenid,
    "MaQuyen" AS maquyen,
    "TenQuyen" AS tenquyen,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "Quyen";;

CREATE VIEW "taikhoan" AS
 SELECT "TaiKhoanID" AS taikhoanid,
    "TenDangNhap" AS tendangnhap,
    "MatKhau" AS matkhau,
    "Email" AS email,
    "SoDienThoai" AS sodienthoai,
    "NhanVienID" AS nhanvienid,
    "KhachHangID" AS khachhangid,
    "TrangThai" AS trangthai,
    "NgayTao" AS ngaytao,
    "UserAuthId" AS userauthid
   FROM "TaiKhoan";;

CREATE VIEW "taikhoan_vaitro" AS
 SELECT "TaiKhoanID" AS taikhoanid,
    "VaiTroID" AS vaitroid
   FROM "TaiKhoan_VaiTro";;

CREATE VIEW "thanhtoan" AS
 SELECT "ThanhToanID" AS thanhtoanid,
    "DonHangID" AS donhangid,
    "SoTien" AS sotien,
    "PhuongThuc" AS phuongthuc,
    "MaGiaoDich" AS magiaodich,
    "ThoiGian" AS thoigian,
    "TrangThai" AS trangthai,
    "GhiChu" AS ghichu
   FROM "ThanhToan";;

CREATE VIEW "thongbao" AS
 SELECT "ThongBaoID" AS thongbaoid,
    "TaiKhoanID" AS taikhoanid,
    "DonHangID" AS donhangid,
    "LoaiThongBao" AS loaithongbao,
    "TieuDe" AS tieude,
    "NoiDung" AS noidung,
    "ThoiGianGui" AS thoigiangui,
    "DaDoc" AS dadoc
   FROM "ThongBao";;

CREATE VIEW "tinnhan" AS
 SELECT "TinNhanID" AS tinnhanid,
    "NguoiGuiID" AS nguoiguiid,
    "NguoiNhanID" AS nguoinhanid,
    "DonHangID" AS donhangid,
    "NoiDung" AS noidung,
    "ThoiGianGui" AS thoigiangui,
    "TrangThai" AS trangthai
   FROM "TinNhan";;

CREATE VIEW "vaitro" AS
 SELECT "VaiTroID" AS vaitroid,
    "TenVaiTro" AS tenvaitro,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "VaiTro";;

CREATE VIEW "vaitro_quyen" AS
 SELECT "VaiTroID" AS vaitroid,
    "QuyenID" AS quyenid
   FROM "VaiTro_Quyen";;

CREATE INDEX "IX_Booking_KhachHangID" ON public."Booking" USING btree ("KhachHangID");
CREATE UNIQUE INDEX booking_idempotency_key_unique_idx ON public."Booking" USING btree ("IdempotencyKey") WHERE ("IdempotencyKey" IS NOT NULL);
CREATE INDEX "IX_ChiTietDonHang_DonHangID" ON public."ChiTietDonHang" USING btree ("DonHangID");
CREATE UNIQUE INDEX "DonHang_IdempotencyKey_unique_idx" ON public."DonHang" USING btree ("IdempotencyKey") WHERE ("IdempotencyKey" IS NOT NULL);
CREATE INDEX "IX_DonHang_KhachHangID" ON public."DonHang" USING btree ("KhachHangID");
CREATE INDEX "IX_DonHang_NhanVienID" ON public."DonHang" USING btree ("NhanVienID");
CREATE INDEX "IX_DonHang_TrangThai" ON public."DonHang" USING btree ("TrangThai");
CREATE UNIQUE INDEX "UX_DonHang_BookingID" ON public."DonHang" USING btree ("BookingID") WHERE ("BookingID" IS NOT NULL);
CREATE UNIQUE INDEX donhang_bookingid_unique_idx ON public."DonHang" USING btree ("BookingID") WHERE ("BookingID" IS NOT NULL);
CREATE INDEX "IX_GiaoNhan_DonHangID" ON public."GiaoNhan" USING btree ("DonHangID");
CREATE UNIQUE INDEX "TaiKhoan_UserAuthId_unique_idx" ON public."TaiKhoan" USING btree ("UserAuthId") WHERE ("UserAuthId" IS NOT NULL);
CREATE INDEX "IX_ThanhToan_DonHangID" ON public."ThanhToan" USING btree ("DonHangID");
CREATE INDEX donhang_trangthai_donhangid_idx ON public.donhang_trangthai USING btree (donhangid, thoigian);
CREATE INDEX khachhang_diachi_khachhangid_idx ON public.khachhang_diachi USING btree (khachhangid);
CREATE UNIQUE INDEX khachhang_diachi_one_default_idx ON public.khachhang_diachi USING btree (khachhangid) WHERE macdinh;
CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);
CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);

-- PERFORMANCE INDEX CANDIDATES (RECOMMENDATIONS ONLY; NOT EXECUTED).
-- Confirm query plans and table cardinality before scheduling any DDL on Supabase.
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_HoaDon_DonHangID" ON public."HoaDon" ("DonHangID");
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_HoaDon_TrangThai_NgayLap" ON public."HoaDon" ("TrangThai", "NgayLap" DESC);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_ThanhToan_DonHangID_TrangThai" ON public."ThanhToan" ("DonHangID", "TrangThai");
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_ChiTietDonHang_DichVuID" ON public."ChiTietDonHang" ("DichVuID");
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_ChiTietDonHang_LoaiDoGiatID" ON public."ChiTietDonHang" ("LoaiDoGiatID");
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_BangGia_Lookup" ON public."BangGia" ("DichVuID", "LoaiDoGiatID", "DonViTinhID", "TrangThai", "NgayApDung" DESC, "BangGiaID" DESC);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_Booking_TrangThai_NgayHen" ON public."Booking" ("TrangThai", "NgayHen");
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS "IX_DichVu_LoaiDichVuID_TrangThai" ON public."DichVu" ("LoaiDichVuID", "TrangThai");
