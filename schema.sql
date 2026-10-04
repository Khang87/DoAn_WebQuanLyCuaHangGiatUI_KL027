-- Supabase PostgreSQL live schema snapshot.
-- Generated from pg_catalog via read-only transactions on 2026-10-04.
-- Schema only: no table rows. This local reference was not executed against Supabase.
--
-- Laravel naming/reference notes for this snapshot:
--   User       -> public."TaiKhoan" ("AvatarURL" stores the public URL; avatar
--                                  files are stored outside PostgreSQL)
--   Customer   -> public."KhachHang" ("SoDienThoai" is unique; "Email" is not)
--   Order      -> public."DonHang" ("TrangThai", "TienGiamDoDiem",
--                                  and "TienGiamKhuyenMai" are the stored fields)
--   Garment    -> public."LoaiDoGiat" ("DanhMucID" is required and references
--                                      "DanhMucLoaiDoGiat")
--   Pricing    -> public."BangGia" (references service, garment, and unit)
-- These are notes about the existing database, not instructions to add columns
-- or constraints. Keep this snapshot aligned with the catalog; do not apply DDL.
SET search_path = public, pg_catalog;

CREATE SCHEMA IF NOT EXISTS private;

CREATE SEQUENCE IF NOT EXISTS "public"."BangGia_BangGiaID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."BangGia_BangGiaID_seq" OWNED BY "public"."BangGia"."BangGiaID";
CREATE SEQUENCE IF NOT EXISTS "public"."Booking_BookingID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."Booking_BookingID_seq" OWNED BY "public"."Booking"."BookingID";
CREATE SEQUENCE IF NOT EXISTS "public"."ChiTietDonHang_ChiTietDonHangID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."ChiTietDonHang_ChiTietDonHangID_seq" OWNED BY "public"."ChiTietDonHang"."ChiTietDonHangID";
CREATE SEQUENCE IF NOT EXISTS "public"."DanhGia_DanhGiaID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."DanhGia_DanhGiaID_seq" OWNED BY "public"."DanhGia"."DanhGiaID";
CREATE SEQUENCE IF NOT EXISTS "public"."DichVu_DichVuID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."DichVu_DichVuID_seq" OWNED BY "public"."DichVu"."DichVuID";
CREATE SEQUENCE IF NOT EXISTS "public"."DiemTichLuy_DiemTichLuyID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."DiemTichLuy_DiemTichLuyID_seq" OWNED BY "public"."DiemTichLuy"."DiemTichLuyID";
CREATE SEQUENCE IF NOT EXISTS "public"."DonHang_DonHangID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."DonHang_DonHangID_seq" OWNED BY "public"."DonHang"."DonHangID";
CREATE SEQUENCE IF NOT EXISTS "public"."DonViTinh_DonViTinhID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."DonViTinh_DonViTinhID_seq" OWNED BY "public"."DonViTinh"."DonViTinhID";
CREATE SEQUENCE IF NOT EXISTS "public"."GiaoNhan_GiaoNhanID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."GiaoNhan_GiaoNhanID_seq" OWNED BY "public"."GiaoNhan"."GiaoNhanID";
CREATE SEQUENCE IF NOT EXISTS "public"."HoaDon_HoaDonID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."HoaDon_HoaDonID_seq" OWNED BY "public"."HoaDon"."HoaDonID";
CREATE SEQUENCE IF NOT EXISTS "public"."KhachHang_KhachHangID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."KhachHang_KhachHangID_seq" OWNED BY "public"."KhachHang"."KhachHangID";
CREATE SEQUENCE IF NOT EXISTS "public"."KhuyenMai_KhuyenMaiID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."KhuyenMai_KhuyenMaiID_seq" OWNED BY "public"."KhuyenMai"."KhuyenMaiID";
CREATE SEQUENCE IF NOT EXISTS "public"."LichSuThayDoiHoaDon_LichSuID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."LichSuThayDoiHoaDon_LichSuID_seq" OWNED BY "public"."LichSuThayDoiHoaDon"."LichSuID";
CREATE SEQUENCE IF NOT EXISTS "public"."LoaiDichVu_LoaiDichVuID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."LoaiDichVu_LoaiDichVuID_seq" OWNED BY "public"."LoaiDichVu"."LoaiDichVuID";
CREATE SEQUENCE IF NOT EXISTS "public"."LoaiDoGiat_LoaiDoGiatID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."LoaiDoGiat_LoaiDoGiatID_seq" OWNED BY "public"."LoaiDoGiat"."LoaiDoGiatID";
CREATE SEQUENCE IF NOT EXISTS "public"."NhanVien_NhanVienID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."NhanVien_NhanVienID_seq" OWNED BY "public"."NhanVien"."NhanVienID";
CREATE SEQUENCE IF NOT EXISTS "public"."Quyen_QuyenID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."Quyen_QuyenID_seq" OWNED BY "public"."Quyen"."QuyenID";
CREATE SEQUENCE IF NOT EXISTS "public"."TaiKhoan_TaiKhoanID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."TaiKhoan_TaiKhoanID_seq" OWNED BY "public"."TaiKhoan"."TaiKhoanID";
CREATE SEQUENCE IF NOT EXISTS "public"."ThanhToan_ThanhToanID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."ThanhToan_ThanhToanID_seq" OWNED BY "public"."ThanhToan"."ThanhToanID";
CREATE SEQUENCE IF NOT EXISTS "public"."ThongBao_ThongBaoID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."ThongBao_ThongBaoID_seq" OWNED BY "public"."ThongBao"."ThongBaoID";
CREATE SEQUENCE IF NOT EXISTS "public"."TinNhan_TinNhanID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."TinNhan_TinNhanID_seq" OWNED BY "public"."TinNhan"."TinNhanID";
CREATE SEQUENCE IF NOT EXISTS "public"."VaiTro_VaiTroID_seq" AS integer INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE;
ALTER SEQUENCE "public"."VaiTro_VaiTroID_seq" OWNED BY "public"."VaiTro"."VaiTroID";

CREATE TABLE IF NOT EXISTS "public"."BangGia" (
    "BangGiaID" integer DEFAULT nextval('"BangGia_BangGiaID_seq"'::regclass) NOT NULL,
    "DichVuID" integer NOT NULL,
    "LoaiDoGiatID" integer NOT NULL,
    "DonViTinhID" integer NOT NULL,
    "DonGia" numeric(18,2) NOT NULL,
    "NgayApDung" date NOT NULL,
    "NgayKetThuc" date,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."Booking" (
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
    "NhanVienID" integer,
    "NhanVienXacNhanID" integer,
    "ThoiGianXacNhan" timestamp without time zone,
    "DiemSuDung" integer DEFAULT 0 NOT NULL,
    "TienGiamDoDiem" numeric(18,2) DEFAULT 0 NOT NULL,
    "DiemDaTru" boolean DEFAULT false NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."ChiTietBooking" (
    "ChiTietBookingID" integer GENERATED BY DEFAULT AS IDENTITY (SEQUENCE NAME "public"."ChiTietBooking_ChiTietBookingID_seq" INCREMENT BY 1 MINVALUE 1 MAXVALUE 2147483647 START WITH 1 CACHE 1 NO CYCLE) NOT NULL,
    "BookingID" integer NOT NULL,
    "DichVuID" integer NOT NULL,
    "LoaiDoGiatID" integer NOT NULL,
    "DonViTinhID" integer NOT NULL,
    "SoLuong" numeric,
    "KhoiLuong" numeric,
    "DonGia" numeric DEFAULT 0 NOT NULL,
    "ThanhTien" numeric DEFAULT 0 NOT NULL,
    "GhiChu" character varying
);

CREATE TABLE IF NOT EXISTS "public"."ChiTietDonHang" (
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
    "TinhTrangTruocKhiGiat" text
);

CREATE TABLE IF NOT EXISTS "public"."DanhGia" (
    "DanhGiaID" integer DEFAULT nextval('"DanhGia_DanhGiaID_seq"'::regclass) NOT NULL,
    "DonHangID" integer NOT NULL,
    "KhachHangID" integer NOT NULL,
    "SoSao" integer NOT NULL,
    "BinhLuan" character varying(1000),
    "NgayDanhGia" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Hiển thị'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."DanhMucLoaiDoGiat" (
    "DanhMucID" bigint GENERATED BY DEFAULT AS IDENTITY (SEQUENCE NAME "public"."DanhMucLoaiDoGiat_DanhMucID_seq" INCREMENT BY 1 MINVALUE 1 MAXVALUE 9223372036854775807 START WITH 1 CACHE 1 NO CYCLE) NOT NULL,
    "TenDanhMuc" character varying(100) NOT NULL,
    "MoTa" text,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    "NgayTao" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."DichVu" (
    "DichVuID" integer DEFAULT nextval('"DichVu_DichVuID_seq"'::regclass) NOT NULL,
    "LoaiDichVuID" integer NOT NULL,
    "TenDichVu" character varying(150) NOT NULL,
    "MoTa" character varying(500),
    "ThoiGianDuKien" integer,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."DiemTichLuy" (
    "DiemTichLuyID" integer DEFAULT nextval('"DiemTichLuy_DiemTichLuyID_seq"'::regclass) NOT NULL,
    "KhachHangID" integer NOT NULL,
    "DiemHienTai" integer DEFAULT 0 NOT NULL,
    "NgayCapNhat" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."DonHang" (
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
    "IdempotencyKey" uuid
);

CREATE TABLE IF NOT EXISTS "public"."DonViTinh" (
    "DonViTinhID" integer DEFAULT nextval('"DonViTinh_DonViTinhID_seq"'::regclass) NOT NULL,
    "TenDonViTinh" character varying(50) NOT NULL,
    "KyHieu" character varying(20),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."GiaoNhan" (
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
    "GhiChu" character varying(500)
);

CREATE TABLE IF NOT EXISTS "public"."HoaDon" (
    "HoaDonID" integer DEFAULT nextval('"HoaDon_HoaDonID_seq"'::regclass) NOT NULL,
    "MaHoaDon" character varying(30) NOT NULL,
    "DonHangID" integer NOT NULL,
    "TongTien" numeric(18,2) NOT NULL,
    "GiamGia" numeric(18,2) DEFAULT 0 NOT NULL,
    "PhiGiaoHang" numeric(18,2) DEFAULT 0 NOT NULL,
    "ThanhTien" numeric(18,2) NOT NULL,
    "NgayLap" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Chưa thanh toán'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."KhachHang" (
    "KhachHangID" integer DEFAULT nextval('"KhachHang_KhachHangID_seq"'::regclass) NOT NULL,
    "HoTen" character varying(100) NOT NULL,
    "SoDienThoai" character varying(15),
    "Email" character varying(150),
    "DiaChi" character varying(255),
    "NgayTao" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."KhuyenMai" (
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
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."LichSuThayDoiHoaDon" (
    "LichSuID" integer DEFAULT nextval('"LichSuThayDoiHoaDon_LichSuID_seq"'::regclass) NOT NULL,
    "HoaDonID" integer NOT NULL,
    "TaiKhoanID" integer NOT NULL,
    "ThoiGian" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TruongThayDoi" character varying(100) NOT NULL,
    "GiaTriCu" character varying(500),
    "GiaTriMoi" character varying(500),
    "LyDo" character varying(500)
);

CREATE TABLE IF NOT EXISTS "public"."LoaiDichVu" (
    "LoaiDichVuID" integer DEFAULT nextval('"LoaiDichVu_LoaiDichVuID_seq"'::regclass) NOT NULL,
    "TenLoaiDichVu" character varying(100) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."LoaiDoGiat" (
    "LoaiDoGiatID" integer DEFAULT nextval('"LoaiDoGiat_LoaiDoGiatID_seq"'::regclass) NOT NULL,
    "TenLoaiDoGiat" character varying(150) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    "DanhMucID" bigint NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."NhanVien" (
    "NhanVienID" integer DEFAULT nextval('"NhanVien_NhanVienID_seq"'::regclass) NOT NULL,
    "HoTen" character varying(100) NOT NULL,
    "SoDienThoai" character varying(15) NOT NULL,
    "Email" character varying(150),
    "DiaChi" character varying(255),
    "ChucDanh" character varying(100),
    "NgayVaoLam" date,
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."NhatKyHeThong" (
    "NhatKyID" bigint GENERATED BY DEFAULT AS IDENTITY (SEQUENCE NAME "public"."NhatKyHeThong_NhatKyID_seq" INCREMENT BY 1 MINVALUE 1 MAXVALUE 9223372036854775807 START WITH 1 CACHE 1 NO CYCLE) NOT NULL,
    "TaiKhoanID" integer,
    "HanhDong" character varying NOT NULL,
    "BangDuLieu" character varying NOT NULL,
    "BanGhiID" bigint,
    "DuLieuCu" jsonb,
    "DuLieuMoi" jsonb,
    "LyDo" character varying,
    "ThoiGian" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "IPAddress" character varying,
    "UserAgent" character varying
);

CREATE TABLE IF NOT EXISTS "public"."Quyen" (
    "QuyenID" integer DEFAULT nextval('"Quyen_QuyenID_seq"'::regclass) NOT NULL,
    "MaQuyen" character varying(100) NOT NULL,
    "TenQuyen" character varying(150) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."TaiKhoan" (
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
    "AvatarURL" text
);

CREATE TABLE IF NOT EXISTS "public"."TaiKhoan_VaiTro" (
    "TaiKhoanID" integer NOT NULL,
    "VaiTroID" integer NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."ThanhToan" (
    "ThanhToanID" integer DEFAULT nextval('"ThanhToan_ThanhToanID_seq"'::regclass) NOT NULL,
    "DonHangID" integer NOT NULL,
    "SoTien" numeric(18,2) NOT NULL,
    "PhuongThuc" character varying(30) NOT NULL,
    "MaGiaoDich" character varying(100),
    "ThoiGian" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Chờ thanh toán'::character varying NOT NULL,
    "GhiChu" character varying(500)
);

CREATE TABLE IF NOT EXISTS "public"."ThongBao" (
    "ThongBaoID" integer DEFAULT nextval('"ThongBao_ThongBaoID_seq"'::regclass) NOT NULL,
    "TaiKhoanID" integer NOT NULL,
    "DonHangID" integer,
    "LoaiThongBao" character varying(50),
    "TieuDe" character varying(200) NOT NULL,
    "NoiDung" character varying(1000) NOT NULL,
    "ThoiGianGui" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "DaDoc" boolean DEFAULT false NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."TinNhan" (
    "TinNhanID" integer DEFAULT nextval('"TinNhan_TinNhanID_seq"'::regclass) NOT NULL,
    "NguoiGuiID" integer NOT NULL,
    "NguoiNhanID" integer NOT NULL,
    "DonHangID" integer,
    "NoiDung" character varying(1000) NOT NULL,
    "ThoiGianGui" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "TrangThai" character varying(30) DEFAULT 'Đã gửi'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."VaiTro" (
    "VaiTroID" integer DEFAULT nextval('"VaiTro_VaiTroID_seq"'::regclass) NOT NULL,
    "TenVaiTro" character varying(100) NOT NULL,
    "MoTa" character varying(255),
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."VaiTro_Quyen" (
    "VaiTroID" integer NOT NULL,
    "QuyenID" integer NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."khachhang_diachi" (
    "diachiid" bigint GENERATED ALWAYS AS IDENTITY (SEQUENCE NAME "public"."khachhang_diachi_diachiid_seq" INCREMENT BY 1 MINVALUE 1 MAXVALUE 9223372036854775807 START WITH 1 CACHE 1 NO CYCLE) NOT NULL,
    "khachhangid" bigint NOT NULL,
    "tennguoinhan" character varying(100),
    "sodienthoai" character varying(15),
    "diachi" character varying(500) NOT NULL,
    "ghichu" character varying(500),
    "macdinh" boolean DEFAULT false NOT NULL,
    "ngaytao" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."sessions" (
    "id" character varying(255) NOT NULL,
    "user_id" bigint,
    "ip_address" character varying(45),
    "user_agent" text,
    "payload" text NOT NULL,
    "last_activity" integer NOT NULL
);

ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_DonGia_check" CHECK ("DonGia" >= 0::numeric);
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Hết hiệu lực'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_pkey" PRIMARY KEY ("BangGiaID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "CK_BangGia_Ngay" CHECK ("NgayKetThuc" IS NULL OR "NgayKetThuc" >= "NgayApDung");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_DiemSuDung_check" CHECK ("DiemSuDung" >= 0);
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_HinhThucNhanDo_check" CHECK ("HinhThucNhanDo"::text = ANY (ARRAY['Tại cửa hàng'::character varying, 'Tại nhà'::character varying]::text[]));
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_MaBooking_key" UNIQUE ("MaBooking");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID") ON DELETE SET NULL;
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_NhanVienXacNhanID_fkey" FOREIGN KEY ("NhanVienXacNhanID") REFERENCES "NhanVien"("NhanVienID");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_TienGiamDoDiem_check" CHECK ("TienGiamDoDiem" >= 0::numeric);
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_pkey" PRIMARY KEY ("BookingID");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "booking_trangthai_check" CHECK ("TrangThai"::text = ANY (ARRAY['ChoTiepNhan'::character varying, 'DaXacNhan'::character varying, 'DaHuy'::character varying, 'HoanThanh'::character varying]::text[]));
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_BookingID_fkey" FOREIGN KEY ("BookingID") REFERENCES "Booking"("BookingID") ON DELETE CASCADE;
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID");
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_DonGia_check" CHECK ("DonGia" >= 0::numeric);
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID");
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_KhoiLuong_check" CHECK ("KhoiLuong" IS NULL OR "KhoiLuong" > 0::numeric);
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID");
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_SoLuong_check" CHECK ("SoLuong" IS NULL OR "SoLuong" > 0::numeric);
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_ThanhTien_check" CHECK ("ThanhTien" >= 0::numeric);
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_measurement_check" CHECK ("SoLuong" IS NOT NULL AND "KhoiLuong" IS NULL OR "SoLuong" IS NULL AND "KhoiLuong" IS NOT NULL);
ALTER TABLE ONLY "public"."ChiTietBooking" ADD CONSTRAINT "ChiTietBooking_pkey" PRIMARY KEY ("ChiTietBookingID");
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "CK_CTDH_SoLuongKhoiLuong" CHECK ("SoLuong" IS NOT NULL AND "SoLuong" > 0::numeric OR "KhoiLuong" IS NOT NULL AND "KhoiLuong" > 0::numeric);
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID");
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_DonGia_check" CHECK ("DonGia" >= 0::numeric);
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID");
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID");
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_ThanhTien_check" CHECK ("ThanhTien" >= 0::numeric);
ALTER TABLE ONLY "public"."ChiTietDonHang" ADD CONSTRAINT "ChiTietDonHang_pkey" PRIMARY KEY ("ChiTietDonHangID");
ALTER TABLE ONLY "public"."DanhGia" ADD CONSTRAINT "DanhGia_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."DanhGia" ADD CONSTRAINT "DanhGia_DonHangID_key" UNIQUE ("DonHangID");
ALTER TABLE ONLY "public"."DanhGia" ADD CONSTRAINT "DanhGia_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID");
ALTER TABLE ONLY "public"."DanhGia" ADD CONSTRAINT "DanhGia_SoSao_check" CHECK ("SoSao" >= 1 AND "SoSao" <= 5);
ALTER TABLE ONLY "public"."DanhGia" ADD CONSTRAINT "DanhGia_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hiển thị'::character varying, 'Ẩn'::character varying]::text[]));
ALTER TABLE ONLY "public"."DanhGia" ADD CONSTRAINT "DanhGia_pkey" PRIMARY KEY ("DanhGiaID");
ALTER TABLE ONLY "public"."DanhMucLoaiDoGiat" ADD CONSTRAINT "CK_DanhMucLoaiDoGiat_TrangThai" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."DanhMucLoaiDoGiat" ADD CONSTRAINT "DanhMucLoaiDoGiat_pkey" PRIMARY KEY ("DanhMucID");
ALTER TABLE ONLY "public"."DichVu" ADD CONSTRAINT "DichVu_LoaiDichVuID_fkey" FOREIGN KEY ("LoaiDichVuID") REFERENCES "LoaiDichVu"("LoaiDichVuID");
ALTER TABLE ONLY "public"."DichVu" ADD CONSTRAINT "DichVu_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."DichVu" ADD CONSTRAINT "DichVu_pkey" PRIMARY KEY ("DichVuID");
ALTER TABLE ONLY "public"."DiemTichLuy" ADD CONSTRAINT "DiemTichLuy_DiemHienTai_check" CHECK ("DiemHienTai" >= 0);
ALTER TABLE ONLY "public"."DiemTichLuy" ADD CONSTRAINT "DiemTichLuy_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID");
ALTER TABLE ONLY "public"."DiemTichLuy" ADD CONSTRAINT "DiemTichLuy_KhachHangID_key" UNIQUE ("KhachHangID");
ALTER TABLE ONLY "public"."DiemTichLuy" ADD CONSTRAINT "DiemTichLuy_pkey" PRIMARY KEY ("DiemTichLuyID");
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_BookingID_fkey" FOREIGN KEY ("BookingID") REFERENCES "Booking"("BookingID");
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_DiemSuDung_check" CHECK ("DiemSuDung" >= 0);
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID");
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_KhuyenMaiID_fkey" FOREIGN KEY ("KhuyenMaiID") REFERENCES "KhuyenMai"("KhuyenMaiID");
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_MaDonHang_key" UNIQUE ("MaDonHang");
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID");
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_PhiGiaoHang_check" CHECK ("PhiGiaoHang" >= 0::numeric);
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_ThanhTien_check" CHECK ("ThanhTien" >= 0::numeric);
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_TienGiamDoDiem_check" CHECK ("TienGiamDoDiem" >= 0::numeric);
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_TienGiamKhuyenMai_check" CHECK ("TienGiamKhuyenMai" >= 0::numeric);
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_TongTien_check" CHECK ("TongTien" >= 0::numeric);
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Chờ tiếp nhận'::character varying, 'Đã tiếp nhận'::character varying, 'Đang giặt'::character varying, 'Hoàn thành giặt'::character varying, 'Đang giao'::character varying, 'Đã giao'::character varying, 'Đã thanh toán'::character varying, 'Đã hủy'::character varying]::text[]));
ALTER TABLE ONLY "public"."DonHang" ADD CONSTRAINT "DonHang_pkey" PRIMARY KEY ("DonHangID");
ALTER TABLE ONLY "public"."DonViTinh" ADD CONSTRAINT "DonViTinh_TenDonViTinh_key" UNIQUE ("TenDonViTinh");
ALTER TABLE ONLY "public"."DonViTinh" ADD CONSTRAINT "DonViTinh_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."DonViTinh" ADD CONSTRAINT "DonViTinh_pkey" PRIMARY KEY ("DonViTinhID");
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_HinhThuc_check" CHECK ("HinhThuc"::text = ANY (ARRAY['Tại cửa hàng'::character varying, 'Tại nhà'::character varying]::text[]));
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_LoaiGiaoNhan_check" CHECK ("LoaiGiaoNhan"::text = ANY (ARRAY['NHAN_DO'::character varying, 'GIAO_DO'::character varying]::text[]));
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID");
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_PhiGiaoNhan_check" CHECK ("PhiGiaoNhan" >= 0::numeric);
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Chờ thực hiện'::character varying, 'Đang thực hiện'::character varying, 'Hoàn thành'::character varying, 'Đã hủy'::character varying]::text[]));
ALTER TABLE ONLY "public"."GiaoNhan" ADD CONSTRAINT "GiaoNhan_pkey" PRIMARY KEY ("GiaoNhanID");
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_DonHangID_key" UNIQUE ("DonHangID");
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_GiamGia_check" CHECK ("GiamGia" >= 0::numeric);
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_MaHoaDon_key" UNIQUE ("MaHoaDon");
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_PhiGiaoHang_check" CHECK ("PhiGiaoHang" >= 0::numeric);
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_ThanhTien_check" CHECK ("ThanhTien" >= 0::numeric);
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_TongTien_check" CHECK ("TongTien" >= 0::numeric);
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Chưa thanh toán'::character varying, 'Đã thanh toán'::character varying, 'Đã hủy'::character varying]::text[]));
ALTER TABLE ONLY "public"."HoaDon" ADD CONSTRAINT "HoaDon_pkey" PRIMARY KEY ("HoaDonID");
ALTER TABLE ONLY "public"."KhachHang" ADD CONSTRAINT "KhachHang_SoDienThoai_key" UNIQUE ("SoDienThoai");
ALTER TABLE ONLY "public"."KhachHang" ADD CONSTRAINT "KhachHang_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Khóa'::character varying, 'Ngừng hoạt động'::character varying]::text[]));
ALTER TABLE ONLY "public"."KhachHang" ADD CONSTRAINT "KhachHang_pkey" PRIMARY KEY ("KhachHangID");
ALTER TABLE ONLY "public"."KhuyenMai" ADD CONSTRAINT "CK_KhuyenMai_Ngay" CHECK ("NgayKetThuc" >= "NgayBatDau");
ALTER TABLE ONLY "public"."KhuyenMai" ADD CONSTRAINT "KhuyenMai_GiaTriGiam_check" CHECK ("GiaTriGiam" >= 0::numeric);
ALTER TABLE ONLY "public"."KhuyenMai" ADD CONSTRAINT "KhuyenMai_LoaiKhuyenMai_check" CHECK ("LoaiKhuyenMai"::text = ANY (ARRAY['Phần trăm'::character varying, 'Tiền mặt'::character varying]::text[]));
ALTER TABLE ONLY "public"."KhuyenMai" ADD CONSTRAINT "KhuyenMai_MaKhuyenMai_key" UNIQUE ("MaKhuyenMai");
ALTER TABLE ONLY "public"."KhuyenMai" ADD CONSTRAINT "KhuyenMai_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying, 'Hết hạn'::character varying]::text[]));
ALTER TABLE ONLY "public"."KhuyenMai" ADD CONSTRAINT "KhuyenMai_pkey" PRIMARY KEY ("KhuyenMaiID");
ALTER TABLE ONLY "public"."LichSuThayDoiHoaDon" ADD CONSTRAINT "LichSuThayDoiHoaDon_HoaDonID_fkey" FOREIGN KEY ("HoaDonID") REFERENCES "HoaDon"("HoaDonID");
ALTER TABLE ONLY "public"."LichSuThayDoiHoaDon" ADD CONSTRAINT "LichSuThayDoiHoaDon_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID");
ALTER TABLE ONLY "public"."LichSuThayDoiHoaDon" ADD CONSTRAINT "LichSuThayDoiHoaDon_pkey" PRIMARY KEY ("LichSuID");
ALTER TABLE ONLY "public"."LoaiDichVu" ADD CONSTRAINT "LoaiDichVu_TenLoaiDichVu_key" UNIQUE ("TenLoaiDichVu");
ALTER TABLE ONLY "public"."LoaiDichVu" ADD CONSTRAINT "LoaiDichVu_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."LoaiDichVu" ADD CONSTRAINT "LoaiDichVu_pkey" PRIMARY KEY ("LoaiDichVuID");
ALTER TABLE ONLY "public"."LoaiDoGiat" ADD CONSTRAINT "FK_LoaiDoGiat_DanhMucLoaiDoGiat" FOREIGN KEY ("DanhMucID") REFERENCES "DanhMucLoaiDoGiat"("DanhMucID") ON UPDATE CASCADE ON DELETE RESTRICT;
ALTER TABLE ONLY "public"."LoaiDoGiat" ADD CONSTRAINT "LoaiDoGiat_TenLoaiDoGiat_key" UNIQUE ("TenLoaiDoGiat");
ALTER TABLE ONLY "public"."LoaiDoGiat" ADD CONSTRAINT "LoaiDoGiat_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."LoaiDoGiat" ADD CONSTRAINT "LoaiDoGiat_pkey" PRIMARY KEY ("LoaiDoGiatID");
ALTER TABLE ONLY "public"."NhanVien" ADD CONSTRAINT "NhanVien_SoDienThoai_key" UNIQUE ("SoDienThoai");
ALTER TABLE ONLY "public"."NhanVien" ADD CONSTRAINT "NhanVien_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Khóa'::character varying, 'Ngừng hoạt động'::character varying]::text[]));
ALTER TABLE ONLY "public"."NhanVien" ADD CONSTRAINT "NhanVien_pkey" PRIMARY KEY ("NhanVienID");
ALTER TABLE ONLY "public"."NhatKyHeThong" ADD CONSTRAINT "NhatKyHeThong_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID");
ALTER TABLE ONLY "public"."NhatKyHeThong" ADD CONSTRAINT "NhatKyHeThong_pkey" PRIMARY KEY ("NhatKyID");
ALTER TABLE ONLY "public"."Quyen" ADD CONSTRAINT "Quyen_MaQuyen_key" UNIQUE ("MaQuyen");
ALTER TABLE ONLY "public"."Quyen" ADD CONSTRAINT "Quyen_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Ngừng hoạt động'::character varying]::text[]));
ALTER TABLE ONLY "public"."Quyen" ADD CONSTRAINT "Quyen_pkey" PRIMARY KEY ("QuyenID");
ALTER TABLE ONLY "public"."TaiKhoan" ADD CONSTRAINT "CK_TaiKhoan_DoiTuong" CHECK ("NhanVienID" IS NOT NULL AND "KhachHangID" IS NULL OR "NhanVienID" IS NULL AND "KhachHangID" IS NOT NULL);
ALTER TABLE ONLY "public"."TaiKhoan" ADD CONSTRAINT "TaiKhoan_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID");
ALTER TABLE ONLY "public"."TaiKhoan" ADD CONSTRAINT "TaiKhoan_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID");
ALTER TABLE ONLY "public"."TaiKhoan" ADD CONSTRAINT "TaiKhoan_TenDangNhap_key" UNIQUE ("TenDangNhap");
ALTER TABLE ONLY "public"."TaiKhoan" ADD CONSTRAINT "TaiKhoan_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Khóa'::character varying, 'Ngừng hoạt động'::character varying]::text[]));
ALTER TABLE ONLY "public"."TaiKhoan" ADD CONSTRAINT "TaiKhoan_pkey" PRIMARY KEY ("TaiKhoanID");
ALTER TABLE ONLY "public"."TaiKhoan_VaiTro" ADD CONSTRAINT "TaiKhoan_VaiTro_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID");
ALTER TABLE ONLY "public"."TaiKhoan_VaiTro" ADD CONSTRAINT "TaiKhoan_VaiTro_VaiTroID_fkey" FOREIGN KEY ("VaiTroID") REFERENCES "VaiTro"("VaiTroID");
ALTER TABLE ONLY "public"."TaiKhoan_VaiTro" ADD CONSTRAINT "TaiKhoan_VaiTro_pkey" PRIMARY KEY ("TaiKhoanID", "VaiTroID");
ALTER TABLE ONLY "public"."ThanhToan" ADD CONSTRAINT "ThanhToan_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."ThanhToan" ADD CONSTRAINT "ThanhToan_PhuongThuc_check" CHECK ("PhuongThuc"::text = ANY (ARRAY['Tiền mặt'::character varying, 'Chuyển khoản'::character varying]::text[]));
ALTER TABLE ONLY "public"."ThanhToan" ADD CONSTRAINT "ThanhToan_SoTien_check" CHECK ("SoTien" > 0::numeric);
ALTER TABLE ONLY "public"."ThanhToan" ADD CONSTRAINT "ThanhToan_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Chờ thanh toán'::character varying, 'Thành công'::character varying, 'Thất bại'::character varying, 'Đã hoàn tiền'::character varying]::text[]));
ALTER TABLE ONLY "public"."ThanhToan" ADD CONSTRAINT "ThanhToan_pkey" PRIMARY KEY ("ThanhToanID");
ALTER TABLE ONLY "public"."ThongBao" ADD CONSTRAINT "ThongBao_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."ThongBao" ADD CONSTRAINT "ThongBao_TaiKhoanID_fkey" FOREIGN KEY ("TaiKhoanID") REFERENCES "TaiKhoan"("TaiKhoanID");
ALTER TABLE ONLY "public"."ThongBao" ADD CONSTRAINT "ThongBao_pkey" PRIMARY KEY ("ThongBaoID");
ALTER TABLE ONLY "public"."TinNhan" ADD CONSTRAINT "TinNhan_DonHangID_fkey" FOREIGN KEY ("DonHangID") REFERENCES "DonHang"("DonHangID");
ALTER TABLE ONLY "public"."TinNhan" ADD CONSTRAINT "TinNhan_NguoiGuiID_fkey" FOREIGN KEY ("NguoiGuiID") REFERENCES "TaiKhoan"("TaiKhoanID");
ALTER TABLE ONLY "public"."TinNhan" ADD CONSTRAINT "TinNhan_NguoiNhanID_fkey" FOREIGN KEY ("NguoiNhanID") REFERENCES "TaiKhoan"("TaiKhoanID");
ALTER TABLE ONLY "public"."TinNhan" ADD CONSTRAINT "TinNhan_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Đã gửi'::character varying, 'Đã nhận'::character varying, 'Đã đọc'::character varying]::text[]));
ALTER TABLE ONLY "public"."TinNhan" ADD CONSTRAINT "TinNhan_pkey" PRIMARY KEY ("TinNhanID");
ALTER TABLE ONLY "public"."VaiTro" ADD CONSTRAINT "VaiTro_TenVaiTro_key" UNIQUE ("TenVaiTro");
ALTER TABLE ONLY "public"."VaiTro" ADD CONSTRAINT "VaiTro_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Ngừng hoạt động'::character varying]::text[]));
ALTER TABLE ONLY "public"."VaiTro" ADD CONSTRAINT "VaiTro_pkey" PRIMARY KEY ("VaiTroID");
ALTER TABLE ONLY "public"."VaiTro_Quyen" ADD CONSTRAINT "VaiTro_Quyen_QuyenID_fkey" FOREIGN KEY ("QuyenID") REFERENCES "Quyen"("QuyenID");
ALTER TABLE ONLY "public"."VaiTro_Quyen" ADD CONSTRAINT "VaiTro_Quyen_VaiTroID_fkey" FOREIGN KEY ("VaiTroID") REFERENCES "VaiTro"("VaiTroID");
ALTER TABLE ONLY "public"."VaiTro_Quyen" ADD CONSTRAINT "VaiTro_Quyen_pkey" PRIMARY KEY ("VaiTroID", "QuyenID");
ALTER TABLE ONLY "public"."khachhang_diachi" ADD CONSTRAINT "khachhang_diachi_diachi_check" CHECK (length(btrim(diachi::text)) > 0);
ALTER TABLE ONLY "public"."khachhang_diachi" ADD CONSTRAINT "khachhang_diachi_khachhangid_fkey" FOREIGN KEY (khachhangid) REFERENCES "KhachHang"("KhachHangID") ON DELETE CASCADE;
ALTER TABLE ONLY "public"."khachhang_diachi" ADD CONSTRAINT "khachhang_diachi_pkey" PRIMARY KEY (diachiid);
ALTER TABLE ONLY "public"."khachhang_diachi" ADD CONSTRAINT "khachhang_diachi_sodienthoai_check" CHECK (sodienthoai IS NULL OR length(btrim(sodienthoai::text)) >= 8);
ALTER TABLE ONLY "public"."sessions" ADD CONSTRAINT "sessions_pkey" PRIMARY KEY (id);

CREATE INDEX "IX_Booking_KhachHangID" ON public."Booking" USING btree ("KhachHangID");
CREATE UNIQUE INDEX booking_idempotency_key_unique_idx ON public."Booking" USING btree ("IdempotencyKey") WHERE ("IdempotencyKey" IS NOT NULL);
CREATE INDEX "idx_ChiTietBooking_BookingID" ON public."ChiTietBooking" USING btree ("BookingID");
CREATE INDEX "idx_ChiTietBooking_DichVuID" ON public."ChiTietBooking" USING btree ("DichVuID");
CREATE INDEX "idx_ChiTietBooking_LoaiDoGiatID" ON public."ChiTietBooking" USING btree ("LoaiDoGiatID");
CREATE INDEX "IX_ChiTietDonHang_DonHangID" ON public."ChiTietDonHang" USING btree ("DonHangID");
CREATE UNIQUE INDEX "DonHang_IdempotencyKey_unique_idx" ON public."DonHang" USING btree ("IdempotencyKey") WHERE ("IdempotencyKey" IS NOT NULL);
CREATE INDEX "IX_DonHang_KhachHangID" ON public."DonHang" USING btree ("KhachHangID");
CREATE INDEX "IX_DonHang_NhanVienID" ON public."DonHang" USING btree ("NhanVienID");
CREATE INDEX "IX_DonHang_TrangThai" ON public."DonHang" USING btree ("TrangThai");
CREATE UNIQUE INDEX "UX_DonHang_BookingID" ON public."DonHang" USING btree ("BookingID") WHERE ("BookingID" IS NOT NULL);
CREATE UNIQUE INDEX donhang_bookingid_unique_idx ON public."DonHang" USING btree ("BookingID") WHERE ("BookingID" IS NOT NULL);
CREATE UNIQUE INDEX "ux_DonHang_BookingID_not_null" ON public."DonHang" USING btree ("BookingID") WHERE ("BookingID" IS NOT NULL);
CREATE INDEX "IX_GiaoNhan_DonHangID" ON public."GiaoNhan" USING btree ("DonHangID");
CREATE INDEX "IX_LoaiDoGiat_DanhMucID" ON public."LoaiDoGiat" USING btree ("DanhMucID");
CREATE INDEX "idx_NhatKyHeThong_BangDuLieu_BanGhiID" ON public."NhatKyHeThong" USING btree ("BangDuLieu", "BanGhiID");
CREATE INDEX "idx_NhatKyHeThong_TaiKhoanID" ON public."NhatKyHeThong" USING btree ("TaiKhoanID");
CREATE INDEX "idx_NhatKyHeThong_ThoiGian" ON public."NhatKyHeThong" USING btree ("ThoiGian");
CREATE UNIQUE INDEX "TaiKhoan_UserAuthId_unique_idx" ON public."TaiKhoan" USING btree ("UserAuthId") WHERE ("UserAuthId" IS NOT NULL);
CREATE INDEX "IX_ThanhToan_DonHangID" ON public."ThanhToan" USING btree ("DonHangID");
CREATE INDEX khachhang_diachi_khachhangid_idx ON public.khachhang_diachi USING btree (khachhangid);
CREATE UNIQUE INDEX khachhang_diachi_one_default_idx ON public.khachhang_diachi USING btree (khachhangid) WHERE macdinh;
CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);
CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.account_id_for_customer(p_khachhangid bigint)
 RETURNS bigint
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT account."TaiKhoanID"
  FROM public."TaiKhoan" AS account
  WHERE account."KhachHangID" = p_khachhangid
    AND account."TrangThai" = 'Hoạt động'
  ORDER BY account."TaiKhoanID"
  LIMIT 1;
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.can_access_order(order_id bigint)
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT (SELECT auth.uid()) IS NOT NULL
    AND EXISTS (
      SELECT 1
      FROM public."DonHang" AS order_record
      WHERE order_record."DonHangID" = order_id
        AND (
          order_record."KhachHangID" = (SELECT private.current_customer_id())
          OR (SELECT private.is_staff())
        )
    );
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.create_legacy_order_invoice()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
BEGIN
	INSERT INTO public.hoadon (
		mahoadon,
		donhangid,
		tongtien,
		giamgia,
		phigiaohang,
		thanhtien,
		trangthai
	) VALUES (
		'HD-' || to_char(clock_timestamp(), 'YYYYMMDDHH24MISS') || '-' ||
			substr(replace(gen_random_uuid()::text, '-', ''), 1, 8),
		NEW."DonHangID",
		NEW."TongTien",
		NEW."TienGiamDoDiem" + NEW."TienGiamKhuyenMai",
		NEW."PhiGiaoHang",
		NEW."ThanhTien",
		'Chưa thanh toán'
	);
	RETURN NEW;
END;
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.create_order_invoice()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
BEGIN
	INSERT INTO public.hoadon (
		mahoadon,
		donhangid,
		tongtien,
		giamgia,
		phigiaohang,
		thanhtien,
		trangthai
	) VALUES (
		'HD-' || to_char(clock_timestamp(), 'YYYYMMDDHH24MISS') || '-' ||
			substr(replace(gen_random_uuid()::text, '-', ''), 1, 8),
		NEW.donhangid,
		NEW.tongtien,
		NEW.tiengiamdodiem + NEW.tiengiamkhuyenmai,
		NEW.phigiaohang,
		NEW.thanhtien,
		'Chưa thanh toán'
	);
	RETURN NEW;
END;
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.current_account_id()
 RETURNS bigint
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT account."TaiKhoanID"
  FROM public."TaiKhoan" AS account
  WHERE account."UserAuthId" = (SELECT auth.uid())
    AND account."TrangThai" = 'Hoạt động';
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.current_customer_id()
 RETURNS bigint
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT account."KhachHangID"
  FROM public."TaiKhoan" AS account
  JOIN public."KhachHang" AS customer
    ON customer."KhachHangID" = account."KhachHangID"
  WHERE account."UserAuthId" = (SELECT auth.uid())
    AND account."TrangThai" = 'Hoạt động'
    AND customer."TrangThai" = 'Hoạt động';
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.current_employee_id()
 RETURNS bigint
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT account."NhanVienID"
  FROM public."TaiKhoan" AS account
  JOIN public."NhanVien" AS employee
    ON employee."NhanVienID" = account."NhanVienID"
  WHERE account."UserAuthId" = (SELECT auth.uid())
    AND account."TrangThai" = 'Hoạt động'
    AND employee."TrangThai" = 'Hoạt động';
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.has_role(role_name text)
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT EXISTS (
    SELECT 1
    FROM public."TaiKhoan" AS account
    JOIN public."TaiKhoan_VaiTro" AS account_role
      ON account_role."TaiKhoanID" = account."TaiKhoanID"
    JOIN public."VaiTro" AS app_role
      ON app_role."VaiTroID" = account_role."VaiTroID"
    WHERE account."UserAuthId" = (SELECT auth.uid())
      AND account."TrangThai" = 'Hoạt động'
      AND app_role."TenVaiTro" = role_name
      AND app_role."TrangThai" = 'Hoạt động'
  );
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.is_staff()
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
			SELECT (SELECT private.has_role('Nhân viên'))
				OR (SELECT private.has_role('Quản lý'))
				OR (SELECT private.has_role('Chủ cửa hàng'));
		$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.normalize_phone(phone_number text)
 RETURNS text
 LANGUAGE sql
 IMMUTABLE
 SET search_path TO ''
AS $function$
			WITH digits AS (
				SELECT regexp_replace(coalesce(phone_number, ''), '[^0-9]', '', 'g') AS value
			)
			SELECT CASE
				WHEN value = '' THEN NULL
				WHEN value LIKE '84%' THEN '+' || value
				WHEN value LIKE '0%' THEN '+84' || substr(value, 2)
				ELSE '+' || value
			END
			FROM digits;
		$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.notify_booking_created()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_account_id bigint;
  customer_name text;
BEGIN
  customer_account_id := private.account_id_for_customer(NEW."KhachHangID");

  SELECT "HoTen"
  INTO customer_name
  FROM public."KhachHang"
  WHERE "KhachHangID" = NEW."KhachHangID";

  IF customer_account_id IS NOT NULL THEN
    INSERT INTO public."ThongBao" (
      "TaiKhoanID", "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc", "DonHangID", "LoaiThongBao"
    )
    SELECT
      customer_account_id::integer,
      'Yêu cầu đặt giặt đã gửi'::varchar,
      ('Yêu cầu ' || NEW."MaBooking" || ' đã được gửi. Cửa hàng sẽ tiếp nhận trong thời gian sớm nhất.')::varchar,
      now()::timestamp,
      false,
      NULL::integer,
      'order_created'::varchar
    WHERE NOT EXISTS (
      SELECT 1
      FROM public."ThongBao" AS notification
      WHERE notification."TaiKhoanID" = customer_account_id
        AND notification."LoaiThongBao" = 'order_created'
        AND notification."DonHangID" IS NULL
        AND notification."NoiDung" LIKE '%' || NEW."MaBooking" || '%'
    );
  END IF;

  PERFORM public.notify_staff_new_booking(
    NEW."BookingID",
    NEW."MaBooking",
    customer_name
  );

  RETURN NEW;
END;
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.notify_customers_new_promotion()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
BEGIN
  IF NEW."TrangThai" = 'Hoạt động'
     AND NEW."NgayBatDau" <= CURRENT_DATE
     AND NEW."NgayKetThuc" >= CURRENT_DATE THEN
    INSERT INTO public."ThongBao" (
      "TaiKhoanID", "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc", "DonHangID", "LoaiThongBao"
    )
    SELECT
      account."TaiKhoanID"::integer,
      'Có khuyến mãi mới'::varchar,
      ('Khuyến mãi ' || NEW."TenKhuyenMai" || ' (' || NEW."MaKhuyenMai" || ') đã sẵn sàng để sử dụng.')::varchar,
      now()::timestamp,
      false,
      NULL::integer,
      'promotion'::varchar
    FROM public."TaiKhoan" AS account
    JOIN public."KhachHang" AS customer
      ON customer."KhachHangID" = account."KhachHangID"
    WHERE account."TrangThai" = 'Hoạt động'
      AND customer."TrangThai" = 'Hoạt động';
  END IF;

  RETURN NEW;
END;
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.record_legacy_order_status_change()
 RETURNS trigger
 LANGUAGE plpgsql
AS $function$
BEGIN
    -- Chỉ ghi nhận khi tạo mới đơn hàng hoặc khi trạng thái đơn hàng bị thay đổi
    IF (TG_OP = 'INSERT') OR (TG_OP = 'UPDATE' AND OLD."TrangThai" IS DISTINCT FROM NEW."TrangThai") THEN
        INSERT INTO public."NhatKyHeThong" (
            "TaiKhoanID",
            "HanhDong",
            "BangDuLieu",
            "BanGhiID",
            "DuLieuCu",
            "DuLieuMoi",
            "LyDo",
            "ThoiGian",
            "IPAddress"
        )
        VALUES (
            NULL,
            'Thay đổi trạng thái đơn hàng',
            'DonHang',
            NEW."DonHangID",
            CASE
                WHEN TG_OP = 'UPDATE' THEN jsonb_build_object('TrangThai', OLD."TrangThai")
                ELSE NULL
            END,
            jsonb_build_object('TrangThai', NEW."TrangThai"),
            NULL,
            CURRENT_TIMESTAMP,
            NULL
        );
    END IF;

    RETURN NEW;
END;
$function$
;

-- Routine schema: private
CREATE OR REPLACE FUNCTION private.record_order_status_change()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
BEGIN
	IF TG_OP = 'INSERT' THEN
		INSERT INTO public.donhang_trangthai (
			donhangid,
			taikhoanid,
			trangthaicu,
			trangthaimoi
		) VALUES (
			NEW.donhangid,
			(SELECT private.current_account_id()),
			NULL,
			NEW.trangthai
		);
	ELSIF OLD.trangthai IS DISTINCT FROM NEW.trangthai THEN
		INSERT INTO public.donhang_trangthai (
			donhangid,
			taikhoanid,
			trangthaicu,
			trangthaimoi
		) VALUES (
			NEW.donhangid,
			(SELECT private.current_account_id()),
			OLD.trangthai,
			NEW.trangthai
		);
	END IF;

	RETURN NEW;
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.cancel_laundry_booking(p_bookingid bigint)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_id bigint := (SELECT private.current_customer_id());
  booking_row public."Booking"%ROWTYPE;
  customer_name text;
BEGIN
  IF (SELECT auth.uid()) IS NULL OR customer_id IS NULL THEN
    RAISE EXCEPTION 'An active customer account is required';
  END IF;

  SELECT *
  INTO booking_row
  FROM public."Booking"
  WHERE "BookingID" = p_bookingid
    AND "KhachHangID" = customer_id
  FOR UPDATE;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Booking not found';
  END IF;

  IF booking_row."TrangThai" <> 'ChoTiepNhan'
     OR EXISTS (SELECT 1 FROM public."DonHang" WHERE "BookingID" = p_bookingid) THEN
    RAISE EXCEPTION 'Only pending bookings can be canceled';
  END IF;

  UPDATE public."Booking"
  SET "TrangThai" = 'DaHuy',
      "NgayCapNhat" = now()
  WHERE "BookingID" = p_bookingid;

  SELECT "HoTen"
  INTO customer_name
  FROM public."KhachHang"
  WHERE "KhachHangID" = customer_id;

  INSERT INTO public."ThongBao" (
    "TaiKhoanID", "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc", "DonHangID", "LoaiThongBao"
  )
  SELECT DISTINCT
    account_role."TaiKhoanID",
    'Yêu cầu đặt giặt bị hủy',
    'Khách hàng ' || coalesce(customer_name, 'Khách hàng') ||
      ' vừa hủy yêu cầu ' || booking_row."MaBooking" || '.',
    now(),
    false,
    NULL,
    'booking_cancelled'
  FROM public."TaiKhoan_VaiTro" AS account_role
  JOIN public."VaiTro" AS role
    ON role."VaiTroID" = account_role."VaiTroID"
  JOIN public."TaiKhoan" AS account
    ON account."TaiKhoanID" = account_role."TaiKhoanID"
  WHERE role."TenVaiTro" IN ('Nhân viên', 'Quản lý', 'Chủ cửa hàng')
    AND role."TrangThai" = 'Hoạt động'
    AND account."TrangThai" = 'Hoạt động';
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.complete_customer_profile(p_full_name text DEFAULT NULL::text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
	current_user_id uuid := (SELECT auth.uid());
	verified_phone text;
	normalized_phone text;
	verified_email text;
	customer_name text := nullif(btrim(p_full_name), '');
	customer_id bigint;
	account_id bigint;
	account_count integer;
	customer_count integer;
	customer_role_id bigint;
BEGIN
	IF current_user_id IS NULL THEN
		RAISE EXCEPTION 'Authentication is required';
	END IF;

	SELECT users.phone, users.email
		INTO verified_phone, verified_email
	FROM auth.users AS users
	WHERE users.id = current_user_id
		AND users.phone_confirmed_at IS NOT NULL;

	IF verified_phone IS NULL THEN
		RAISE EXCEPTION 'A verified phone number is required';
	END IF;

	normalized_phone := private.normalize_phone(verified_phone);
	IF normalized_phone IS NULL THEN
		RAISE EXCEPTION 'A valid phone number is required';
	END IF;

	SELECT account.taikhoanid, account.khachhangid
		INTO account_id, customer_id
	FROM public.taikhoan AS account
	WHERE account.userauthid = current_user_id
		AND account.trangthai = 'Hoạt động';

	IF account_id IS NOT NULL THEN
		IF customer_id IS NOT NULL AND customer_name IS NOT NULL THEN
			UPDATE public.khachhang
			SET hoten = customer_name,
					email = coalesce(verified_email, email)
			WHERE khachhangid = customer_id;
		END IF;
		RETURN;
	END IF;

	SELECT count(*)::integer, min(account.taikhoanid)
		INTO account_count, account_id
	FROM public.taikhoan AS account
	WHERE private.normalize_phone(account.sodienthoai) = normalized_phone;

	IF account_count > 1 THEN
		RAISE EXCEPTION 'Multiple accounts use this phone number';
	END IF;

	IF account_id IS NOT NULL THEN
		SELECT account.khachhangid
			INTO customer_id
		FROM public.taikhoan AS account
		WHERE account.taikhoanid = account_id
		FOR UPDATE;

		IF customer_id IS NULL THEN
			RAISE EXCEPTION 'Staff accounts must be linked by a manager';
		END IF;

		UPDATE public.taikhoan
		SET userauthid = current_user_id,
			email = coalesce(verified_email, email)
		WHERE taikhoanid = account_id
			AND (userauthid IS NULL OR userauthid = current_user_id)
			AND trangthai = 'Hoạt động';

		IF NOT FOUND THEN
			RAISE EXCEPTION 'This customer account cannot be linked';
		END IF;

		IF customer_name IS NOT NULL THEN
			UPDATE public.khachhang
			SET hoten = customer_name,
				email = coalesce(verified_email, email)
			WHERE khachhangid = customer_id;
		END IF;
	ELSE
		SELECT count(*)::integer, min(customer.khachhangid)
			INTO customer_count, customer_id
		FROM public.khachhang AS customer
		WHERE private.normalize_phone(customer.sodienthoai) = normalized_phone;

		IF customer_count > 1 THEN
			RAISE EXCEPTION 'Multiple customer profiles use this phone number';
		END IF;

		IF customer_id IS NULL THEN
			IF customer_name IS NULL THEN
				RAISE EXCEPTION 'A full name is required to create a customer profile';
			END IF;

			INSERT INTO public.khachhang (hoten, sodienthoai, email)
			VALUES (customer_name, verified_phone, verified_email)
			RETURNING khachhangid INTO customer_id;
		ELSIF customer_name IS NOT NULL THEN
			UPDATE public.khachhang
			SET hoten = customer_name,
					email = coalesce(verified_email, email)
			WHERE khachhangid = customer_id;
		END IF;

		SELECT count(*)::integer, min(account.taikhoanid)
			INTO account_count, account_id
		FROM public.taikhoan AS account
		WHERE account.khachhangid = customer_id;

		IF account_count > 1 THEN
			RAISE EXCEPTION 'Multiple accounts are attached to this customer profile';
		END IF;

		IF account_id IS NULL THEN
			INSERT INTO public.taikhoan (
				tendangnhap,
				matkhau,
				email,
				sodienthoai,
				khachhangid,
				userauthid
			) VALUES (
				'auth-' || current_user_id::text,
				NULL,
				verified_email,
				verified_phone,
				customer_id,
				current_user_id
			)
			RETURNING taikhoanid INTO account_id;
		ELSE
			UPDATE public.taikhoan
			SET userauthid = current_user_id,
					email = coalesce(verified_email, email)
			WHERE taikhoanid = account_id
				AND userauthid IS NULL;

			IF NOT FOUND THEN
				RAISE EXCEPTION 'This customer profile is already linked to another account';
			END IF;
		END IF;
	END IF;

	IF customer_id IS NOT NULL THEN
		SELECT role.vaitroid INTO customer_role_id
		FROM public.vaitro AS role
		WHERE role.tenvaitro = 'Khách hàng'
			AND role.trangthai = 'Hoạt động';

		IF customer_role_id IS NULL THEN
			RAISE EXCEPTION 'The active customer role is not configured';
		END IF;

		INSERT INTO public.taikhoan_vaitro (taikhoanid, vaitroid)
		VALUES (account_id, customer_role_id)
		ON CONFLICT DO NOTHING;
	END IF;
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.complete_google_customer_profile(p_full_name text DEFAULT NULL::text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  current_user_id uuid := (SELECT auth.uid());
  verified_email text;
  customer_name text;
  customer_id bigint;
  account_id bigint;
  customer_role_id bigint;
BEGIN
  IF current_user_id IS NULL THEN
    RAISE EXCEPTION 'Authentication is required';
  END IF;

  SELECT users.email,
    coalesce(
      nullif(btrim(p_full_name), ''),
      nullif(btrim(users.raw_user_meta_data ->> 'full_name'), ''),
      nullif(btrim(users.raw_user_meta_data ->> 'name'), ''),
      users.email
    )
  INTO verified_email, customer_name
  FROM auth.users AS users
  WHERE users.id = current_user_id;

  IF verified_email IS NULL THEN
    RAISE EXCEPTION 'A verified Google email is required';
  END IF;

  IF to_regclass('public.taikhoan') IS NOT NULL THEN
    SELECT account.taikhoanid, account.khachhangid
    INTO account_id, customer_id
    FROM public.taikhoan AS account
    WHERE account.userauthid = current_user_id
      AND account.trangthai = 'Hoạt động';

    IF account_id IS NOT NULL THEN
      UPDATE public.khachhang
      SET hoten = customer_name, email = verified_email
      WHERE khachhangid = customer_id;
      RETURN;
    END IF;

    SELECT customer.khachhangid INTO customer_id
    FROM public.khachhang AS customer
    WHERE lower(customer.email) = lower(verified_email)
    LIMIT 1;

    IF customer_id IS NULL THEN
      INSERT INTO public.khachhang (hoten, sodienthoai, email)
      VALUES (customer_name, NULL, verified_email)
      RETURNING khachhangid INTO customer_id;
    ELSE
      UPDATE public.khachhang
      SET hoten = customer_name, email = verified_email
      WHERE khachhangid = customer_id;
    END IF;

    INSERT INTO public.taikhoan (
      tendangnhap, matkhau, email, sodienthoai, khachhangid, userauthid
    ) VALUES (
      'auth-' || current_user_id::text, NULL, verified_email, NULL,
      customer_id, current_user_id
    )
    RETURNING taikhoanid INTO account_id;

    SELECT role.vaitroid INTO customer_role_id
    FROM public.vaitro AS role
    WHERE role.tenvaitro = 'Khách hàng'
      AND role.trangthai = 'Hoạt động';

    IF customer_role_id IS NULL THEN
      RAISE EXCEPTION 'The active customer role is not configured';
    END IF;

    INSERT INTO public.taikhoan_vaitro (taikhoanid, vaitroid)
    VALUES (account_id, customer_role_id)
    ON CONFLICT DO NOTHING;
  ELSE
    SELECT account."TaiKhoanID", account."KhachHangID"
    INTO account_id, customer_id
    FROM public."TaiKhoan" AS account
    WHERE account."UserAuthId" = current_user_id
      AND account."TrangThai" = 'Hoạt động';

    IF account_id IS NOT NULL THEN
      UPDATE public."KhachHang"
      SET "HoTen" = customer_name, "Email" = verified_email
      WHERE "KhachHangID" = customer_id;
      RETURN;
    END IF;

    SELECT customer."KhachHangID" INTO customer_id
    FROM public."KhachHang" AS customer
    WHERE lower(customer."Email") = lower(verified_email)
    LIMIT 1;

    IF customer_id IS NULL THEN
      INSERT INTO public."KhachHang" ("HoTen", "SoDienThoai", "Email")
      VALUES (customer_name, NULL, verified_email)
      RETURNING "KhachHangID" INTO customer_id;
    ELSE
      UPDATE public."KhachHang"
      SET "HoTen" = customer_name, "Email" = verified_email
      WHERE "KhachHangID" = customer_id;
    END IF;

    INSERT INTO public."TaiKhoan" (
      "TenDangNhap", "MatKhau", "Email", "SoDienThoai",
      "KhachHangID", "UserAuthId"
    ) VALUES (
      'auth-' || current_user_id::text, NULL, verified_email, NULL,
      customer_id, current_user_id
    )
    RETURNING "TaiKhoanID" INTO account_id;

    SELECT role."VaiTroID" INTO customer_role_id
    FROM public."VaiTro" AS role
    WHERE role."TenVaiTro" = 'Khách hàng'
      AND role."TrangThai" = 'Hoạt động';

    IF customer_role_id IS NULL THEN
      RAISE EXCEPTION 'The active customer role is not configured';
    END IF;

    INSERT INTO public."TaiKhoan_VaiTro" ("TaiKhoanID", "VaiTroID")
    VALUES (account_id, customer_role_id)
    ON CONFLICT DO NOTHING;
  END IF;
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.confirm_laundry_booking(p_bookingid bigint)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$

DECLARE

    booking_row public."Booking"%ROWTYPE;

    order_id bigint;
    order_number text;

    current_employee bigint :=
        (SELECT private.current_employee_id());

    pickup_at timestamp with time zone;

    total_amount numeric(18,2);

    detail_count integer;

BEGIN

    -- =====================================================
    -- 1. Kiểm tra quyền nhân viên
    -- =====================================================

    IF (SELECT auth.uid()) IS NULL
       OR NOT (SELECT private.is_staff())
       OR (
           current_employee IS NULL
           AND NOT (SELECT private.has_role('Quản lý'))
           AND NOT (SELECT private.has_role('Chủ cửa hàng'))
       ) THEN

        RAISE EXCEPTION
            'An active staff account is required';

    END IF;


    -- =====================================================
    -- 2. Khóa Booking
    -- =====================================================

    SELECT *
    INTO booking_row
    FROM public."Booking"
    WHERE "BookingID" = p_bookingid
    FOR UPDATE;


    IF NOT FOUND THEN

        RAISE EXCEPTION
            'Booking not found';

    END IF;


    -- =====================================================
    -- 3. Idempotent:
    -- Nếu Booking đã xác nhận và đã có Order
    -- thì trả Order cũ
    -- =====================================================

    IF booking_row."TrangThai" = 'DaXacNhan' THEN

        SELECT "DonHangID"
        INTO order_id
        FROM public."DonHang"
        WHERE "BookingID" = p_bookingid;


        IF order_id IS NOT NULL THEN

            RETURN jsonb_build_object(
                'donhangid',
                order_id,

                'bookingid',
                p_bookingid
            );

        END IF;

    END IF;


    -- =====================================================
    -- 4. Chỉ Booking đang chờ tiếp nhận
    -- mới được xác nhận
    -- =====================================================

    IF booking_row."TrangThai" <> 'ChoTiepNhan' THEN

        RAISE EXCEPTION
            'Only pending bookings can be confirmed';

    END IF;


    -- =====================================================
    -- 5. Booking phải có ít nhất 1 ChiTietBooking
    -- =====================================================

    SELECT
        COUNT(*),
        COALESCE(
            SUM("ThanhTien"),
            0
        )

    INTO
        detail_count,
        total_amount

    FROM public."ChiTietBooking"

    WHERE "BookingID" = p_bookingid;


    IF detail_count = 0 THEN

        RAISE EXCEPTION
            'Booking must contain at least one detail';

    END IF;


    -- =====================================================
    -- 6. Sinh mã đơn hàng
    -- =====================================================

    order_number :=
        'DH-' ||
        to_char(
            clock_timestamp(),
            'YYYYMMDDHH24MISS'
        ) ||
        '-' ||
        substr(
            replace(
                gen_random_uuid()::text,
                '-',
                ''
            ),
            1,
            8
        );


    -- =====================================================
    -- 7. Tạo DonHang chính thức
    -- =====================================================

    INSERT INTO public."DonHang" (
        "MaDonHang",
        "BookingID",
        "KhachHangID",
        "NhanVienID",
        "TrangThai",
        "TongTien",
        "PhiGiaoHang",
        "ThanhTien",
        "GhiChu"
    )

    VALUES (
        order_number,
        p_bookingid,
        booking_row."KhachHangID",
        current_employee,
        'Đã tiếp nhận',
        total_amount,
        0,
        total_amount,
        booking_row."GhiChu"
    )

    RETURNING "DonHangID"
    INTO order_id;


    -- =====================================================
    -- 8. Copy tất cả ChiTietBooking
    -- -> ChiTietDonHang
    -- =====================================================

    INSERT INTO public."ChiTietDonHang" (
        "DonHangID",
        "DichVuID",
        "LoaiDoGiatID",
        "DonViTinhID",
        "SoLuong",
        "KhoiLuong",
        "DonGia",
        "ThanhTien",
        "GhiChu"
    )

    SELECT
        order_id,
        cb."DichVuID",
        cb."LoaiDoGiatID",
        cb."DonViTinhID",
        cb."SoLuong",
        cb."KhoiLuong",
        cb."DonGia",
        cb."ThanhTien",
        cb."GhiChu"

    FROM public."ChiTietBooking" AS cb

    WHERE cb."BookingID" = p_bookingid

    ORDER BY cb."ChiTietBookingID";


    -- =====================================================
    -- 9. Nếu nhận đồ tại nhà
    -- -> tạo GiaoNhan NHAN_DO
    -- =====================================================

    IF booking_row."HinhThucNhanDo" = 'Tại nhà' THEN

        pickup_at :=
            (
                booking_row."NgayHen" +
                booking_row."GioHen"
            )
            AT TIME ZONE 'Asia/Ho_Chi_Minh';


        INSERT INTO public."GiaoNhan" (
            "DonHangID",
            "LoaiGiaoNhan",
            "HinhThuc",
            "DiaChi",
            "ThoiGianDuKien",
            "TrangThai"
        )

        VALUES (
            order_id,
            'NHAN_DO',
            'Tại nhà',
            booking_row."DiaChiNhan",
            pickup_at,
            'Chờ thực hiện'
        );

    END IF;


    -- =====================================================
    -- 10. Cập nhật Booking
    -- =====================================================

    UPDATE public."Booking"

    SET
        "TrangThai" = 'DaXacNhan',

        "NhanVienXacNhanID" = current_employee,

        "ThoiGianXacNhan" = now(),

        "NgayCapNhat" = now()

    WHERE "BookingID" = p_bookingid;


    -- =====================================================
    -- 11. Trả kết quả
    -- =====================================================

    RETURN jsonb_build_object(

        'donhangid',
        order_id,

        'madonhang',
        order_number,

        'bookingid',
        p_bookingid,

        'trangthai',
        'Đã tiếp nhận',

        'tongtien',
        total_amount,

        'sochitiet',
        detail_count

    );

END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.confirm_laundry_booking_without_details(p_bookingid bigint)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  booking_row public."Booking"%ROWTYPE;
  order_id bigint;
  order_number text;
  current_employee bigint := (SELECT private.current_employee_id());
  pickup_at timestamptz;
BEGIN
  IF (SELECT auth.uid()) IS NULL
    OR NOT (SELECT private.is_staff())
    OR (current_employee IS NULL
        AND NOT (SELECT private.has_role('Quản lý'))
        AND NOT (SELECT private.has_role('Chủ cửa hàng'))) THEN
    RAISE EXCEPTION 'An active staff account is required';
  END IF;

  SELECT * INTO booking_row
  FROM public."Booking"
  WHERE "BookingID" = p_bookingid
  FOR UPDATE;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Booking not found';
  END IF;

  SELECT "DonHangID" INTO order_id
  FROM public."DonHang"
  WHERE "BookingID" = p_bookingid;
  IF order_id IS NOT NULL THEN
    RETURN jsonb_build_object('donhangid', order_id, 'bookingid', p_bookingid);
  END IF;

  IF booking_row."TrangThai" <> 'ChoTiepNhan' THEN
    RAISE EXCEPTION 'Only pending bookings can be confirmed';
  END IF;

  IF EXISTS (
    SELECT 1 FROM public."ChiTietBooking"
    WHERE "BookingID" = p_bookingid
  ) THEN
    RAISE EXCEPTION 'Booking already contains laundry details';
  END IF;

  order_number := 'DH-' || to_char(clock_timestamp(), 'YYYYMMDDHH24MISS') ||
    '-' || substr(replace(gen_random_uuid()::text, '-', ''), 1, 8);

  INSERT INTO public."DonHang" (
    "MaDonHang", "BookingID", "KhachHangID", "NhanVienID", "TrangThai",
    "TongTien", "PhiGiaoHang", "ThanhTien", "GhiChu"
  ) VALUES (
    order_number, p_bookingid, booking_row."KhachHangID", current_employee,
    'Đã tiếp nhận', 0, 0, 0,
    coalesce(booking_row."GhiChu" || E'\n', '') ||
      'Khách hàng yêu cầu cửa hàng kiểm nhận và báo giá.'
  ) RETURNING "DonHangID" INTO order_id;

  IF booking_row."HinhThucNhanDo" = 'Tại nhà' THEN
    pickup_at := (booking_row."NgayHen" + booking_row."GioHen")
      AT TIME ZONE 'Asia/Ho_Chi_Minh';
    INSERT INTO public."GiaoNhan" (
      "DonHangID", "LoaiGiaoNhan", "HinhThuc", "DiaChi",
      "ThoiGianDuKien", "TrangThai"
    ) VALUES (
      order_id, 'NHAN_DO', 'Tại nhà', booking_row."DiaChiNhan", pickup_at,
      'Chờ thực hiện'
    );
  END IF;

  UPDATE public."Booking"
  SET "TrangThai" = 'DaXacNhan',
      "NhanVienXacNhanID" = current_employee,
      "ThoiGianXacNhan" = now(),
      "NgayCapNhat" = now()
  WHERE "BookingID" = p_bookingid;

  RETURN jsonb_build_object(
    'donhangid', order_id,
    'madonhang', order_number,
    'bookingid', p_bookingid,
    'trangthai', 'Đã tiếp nhận',
    'tongtien', 0,
    'sochitiet', 0
  );
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.confirm_order_payment(p_thanhtoanid bigint, p_success boolean, p_ghichu text DEFAULT NULL::text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE

    current_employee bigint :=
        (SELECT private.current_employee_id());

    payment public."ThanhToan"%ROWTYPE;

    order_record public."DonHang"%ROWTYPE;

    invoice public."HoaDon"%ROWTYPE;

    paid_total numeric(18,2);

BEGIN

    -- =====================================================
    -- 1. Kiểm tra quyền nhân viên
    -- =====================================================

    IF (SELECT auth.uid()) IS NULL
       OR NOT (SELECT private.is_staff())
       OR (
           current_employee IS NULL
           AND NOT (SELECT private.has_role('Quản lý'))
           AND NOT (SELECT private.has_role('Chủ cửa hàng'))
       ) THEN

        RAISE EXCEPTION
            'An active staff account is required';

    END IF;


    -- =====================================================
    -- 2. Lấy và khóa Payment
    -- =====================================================

    SELECT *
    INTO payment

    FROM public."ThanhToan"

    WHERE "ThanhToanID" = p_thanhtoanid

    FOR UPDATE;


    IF NOT FOUND
       OR payment."TrangThai" <> 'Chờ thanh toán' THEN

        RAISE EXCEPTION
            'Pending payment not found';

    END IF;


    -- =====================================================
    -- 3. Lấy và khóa Order
    -- =====================================================

    SELECT *
    INTO order_record

    FROM public."DonHang"

    WHERE "DonHangID" = payment."DonHangID"

    FOR UPDATE;


    IF NOT FOUND THEN

        RAISE EXCEPTION
            'Order not found';

    END IF;


    -- =====================================================
    -- 4. Lấy và khóa Invoice
    -- =====================================================

    SELECT *
    INTO invoice

    FROM public."HoaDon"

    WHERE "DonHangID" = payment."DonHangID"

    FOR UPDATE;


    IF NOT FOUND THEN

        RAISE EXCEPTION
            'Invoice not found';

    END IF;


    -- =====================================================
    -- 5. Thanh toán thành công
    -- =====================================================

    IF p_success THEN

        UPDATE public."ThanhToan"

        SET
            "TrangThai" = 'Thành công',

            "GhiChu" =
                left(
                    coalesce(
                        nullif(
                            btrim(p_ghichu),
                            ''
                        ),
                        "GhiChu"
                    ),
                    500
                )

        WHERE "ThanhToanID" = p_thanhtoanid;


        -- Tổng tiền đã thanh toán thành công
        SELECT
            coalesce(
                sum("SoTien"),
                0
            )

        INTO paid_total

        FROM public."ThanhToan"

        WHERE "DonHangID" = payment."DonHangID"

          AND "TrangThai" = 'Thành công';


        -- Nếu đã thanh toán đủ
        IF paid_total >= invoice."ThanhTien" THEN

            UPDATE public."HoaDon"

            SET
                "TrangThai" = 'Đã thanh toán'

            WHERE "HoaDonID" = invoice."HoaDonID";


            -- Chỉ chuyển Order sang Đã thanh toán
            -- nếu hiện tại đang Đã giao
            UPDATE public."DonHang"

            SET
                "TrangThai" = 'Đã thanh toán',

                "NgayCapNhat" = now()

            WHERE "DonHangID" = order_record."DonHangID"

              AND "TrangThai" = 'Đã giao';

        END IF;


    -- =====================================================
    -- 6. Thanh toán thất bại
    -- =====================================================

    ELSE

        UPDATE public."ThanhToan"

        SET
            "TrangThai" = 'Thất bại',

            "GhiChu" =
                left(
                    coalesce(
                        nullif(
                            btrim(p_ghichu),
                            ''
                        ),
                        "GhiChu"
                    ),
                    500
                )

        WHERE "ThanhToanID" = p_thanhtoanid;

    END IF;

END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.get_current_roles()
 RETURNS text[]
 LANGUAGE plpgsql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  current_user_id uuid := (SELECT auth.uid());
  account_roles text[];
BEGIN
  IF current_user_id IS NULL THEN
    RETURN ARRAY[]::text[];
  END IF;

  IF to_regclass('public.taikhoan') IS NOT NULL THEN
    SELECT coalesce(array_agg(role.tenvaitro), ARRAY[]::text[])
    INTO account_roles
    FROM public.taikhoan AS account
    JOIN public.taikhoan_vaitro AS account_role
      ON account_role.taikhoanid = account.taikhoanid
    JOIN public.vaitro AS role ON role.vaitroid = account_role.vaitroid
    WHERE account.userauthid = current_user_id
      AND account.trangthai = 'Hoạt động'
      AND role.trangthai = 'Hoạt động';
  ELSE
    SELECT coalesce(array_agg(role."TenVaiTro"), ARRAY[]::text[])
    INTO account_roles
    FROM public."TaiKhoan" AS account
    JOIN public."TaiKhoan_VaiTro" AS account_role
      ON account_role."TaiKhoanID" = account."TaiKhoanID"
    JOIN public."VaiTro" AS role
      ON role."VaiTroID" = account_role."VaiTroID"
    WHERE account."UserAuthId" = current_user_id
      AND account."TrangThai" = 'Hoạt động'
      AND role."TrangThai" = 'Hoạt động';
  END IF;

  RETURN coalesce(account_roles, ARRAY[]::text[]);
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.get_customer_loyalty()
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_id bigint := (SELECT private.current_customer_id());
  current_points integer;
  active_vouchers jsonb;
BEGIN
  IF customer_id IS NULL THEN
    RAISE EXCEPTION 'An active customer account is required';
  END IF;

  SELECT coalesce(points."DiemHienTai", 0)
  INTO current_points
  FROM public."DiemTichLuy" AS points
  WHERE points."KhachHangID" = customer_id;

  SELECT coalesce(
    jsonb_agg(
      jsonb_build_object(
        'khuyenmaiid', promotion."KhuyenMaiID",
        'makhuyenmai', promotion."MaKhuyenMai",
        'tenkhuyenmai', promotion."TenKhuyenMai",
        'loaikhuyenmai', promotion."LoaiKhuyenMai",
        'giatrigiam', promotion."GiaTriGiam",
        'giatridontoithieu', promotion."GiaTriDonToiThieu",
        'mucgiamtoida', promotion."MucGiamToiDa",
        'dieukienapdung', promotion."DieuKienApDung"
      )
      ORDER BY promotion."NgayKetThuc"
    ),
    '[]'::jsonb
  )
  INTO active_vouchers
  FROM public."KhuyenMai" AS promotion
  WHERE promotion."TrangThai" = 'Hoạt động'
    AND promotion."NgayBatDau" <= current_date
    AND promotion."NgayKetThuc" >= current_date
    AND (
      promotion."SoLuongSuDung" IS NULL
      OR promotion."SoLuongSuDung" > 0
    );

  RETURN jsonb_build_object(
    'points', coalesce(current_points, 0),
    'vouchers', active_vouchers
  );
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.get_laundry_order_status_history(p_donhangid bigint)
 RETURNS TABLE(trangthaicu text, trangthaimoi text, lydo text, thoigian timestamp without time zone)
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT
    event."DuLieuCu" ->> 'TrangThai',
    event."DuLieuMoi" ->> 'TrangThai',
    event."LyDo"::text,
    event."ThoiGian"
  FROM public."NhatKyHeThong" AS event
  WHERE (SELECT private.can_access_order(p_donhangid))
    AND event."BangDuLieu" = 'DonHang'
    AND event."BanGhiID" = p_donhangid
    AND event."HanhDong" = 'Thay đổi trạng thái đơn hàng'
  ORDER BY event."ThoiGian";
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.notify_staff_new_booking(p_bookingid bigint, p_booking_number text, p_customer_name text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
BEGIN
  INSERT INTO public."ThongBao" (
    "TaiKhoanID", "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc", "DonHangID", "LoaiThongBao"
  )
  SELECT DISTINCT
    account_role."TaiKhoanID"::integer,
    'Có yêu cầu đặt giặt mới'::varchar,
    ('Khách hàng ' || coalesce(nullif(btrim(p_customer_name), ''), 'Khách hàng') ||
      ' vừa gửi yêu cầu ' || coalesce(nullif(btrim(p_booking_number), ''), '#' || p_bookingid::text) || '.')::varchar,
    now()::timestamp,
    false,
    NULL::integer,
    'new_booking'::varchar
  FROM public."TaiKhoan_VaiTro" AS account_role
  JOIN public."VaiTro" AS role
    ON role."VaiTroID" = account_role."VaiTroID"
  JOIN public."TaiKhoan" AS account
    ON account."TaiKhoanID" = account_role."TaiKhoanID"
  WHERE role."TenVaiTro" IN ('Nhân viên', 'Quản lý', 'Chủ cửa hàng')
    AND role."TrangThai" = 'Hoạt động'
    AND account."TrangThai" = 'Hoạt động'
    AND NOT EXISTS (
      SELECT 1
      FROM public."ThongBao" AS notification
      WHERE notification."TaiKhoanID" = account_role."TaiKhoanID"
        AND notification."LoaiThongBao" = 'new_booking'
        AND notification."DonHangID" IS NULL
        AND notification."NoiDung" LIKE '%' || coalesce(nullif(btrim(p_booking_number), ''), '#' || p_bookingid::text) || '%'
    );
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.request_order_payment(p_donhangid bigint, p_phuongthuc text, p_idempotency_key uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE

    customer_id bigint :=
        (SELECT private.current_customer_id());

    order_record public."DonHang"%ROWTYPE;

    invoice public."HoaDon"%ROWTYPE;

    existing_payment public."ThanhToan"%ROWTYPE;

    amount_due numeric(18,2);

    payment_id bigint;

BEGIN

    -- =====================================================
    -- 1. Kiểm tra khách hàng
    -- =====================================================

    IF (SELECT auth.uid()) IS NULL
       OR customer_id IS NULL THEN

        RAISE EXCEPTION
            'An active customer account is required';

    END IF;


    -- =====================================================
    -- 2. Kiểm tra phương thức thanh toán
    -- =====================================================

    IF p_phuongthuc NOT IN (
        'Tiền mặt',
        'Chuyển khoản'
    ) THEN

        RAISE EXCEPTION
            'Unsupported payment method';

    END IF;


    -- =====================================================
    -- 3. Kiểm tra Idempotency
    -- =====================================================

    IF p_idempotency_key IS NULL THEN

        RAISE EXCEPTION
            'An idempotency key is required';

    END IF;


    PERFORM pg_catalog.pg_advisory_xact_lock(
        pg_catalog.hashtextextended(
            p_idempotency_key::text,
            0
        )
    );


    -- =====================================================
    -- 4. Kiểm tra Payment đã tạo trước đó
    -- =====================================================

    SELECT *
    INTO existing_payment

    FROM public."ThanhToan"

    WHERE "IdempotencyKey" =
          p_idempotency_key;


    IF FOUND THEN

        IF NOT EXISTS (

            SELECT 1

            FROM public."DonHang" AS order_row

            WHERE order_row."DonHangID" =
                  existing_payment."DonHangID"

              AND order_row."KhachHangID" =
                  customer_id

        ) THEN

            RAISE EXCEPTION
                'Idempotency key is already in use';

        END IF;


        RETURN jsonb_build_object(

            'thanhtoanid',
            existing_payment."ThanhToanID",

            'sotien',
            existing_payment."SoTien",

            'trangthai',
            existing_payment."TrangThai",

            'phuongthuc',
            existing_payment."PhuongThuc"

        );

    END IF;


    -- =====================================================
    -- 5. Lấy và khóa Order
    -- =====================================================

    SELECT *
    INTO order_record

    FROM public."DonHang"

    WHERE "DonHangID" = p_donhangid

      AND "KhachHangID" = customer_id

    FOR UPDATE;


    IF NOT FOUND THEN

        RAISE EXCEPTION
            'Order not found';

    END IF;


    -- Chỉ thanh toán sau khi giao
    IF order_record."TrangThai" NOT IN (
        'Đã giao',
        'Đã thanh toán'
    ) THEN

        RAISE EXCEPTION
            'Payment is available after the order is delivered';

    END IF;


    -- =====================================================
    -- 6. Lấy Invoice
    -- =====================================================

    SELECT *
    INTO invoice

    FROM public."HoaDon"

    WHERE "DonHangID" = p_donhangid

    FOR UPDATE;


    IF NOT FOUND
       OR invoice."TrangThai" <> 'Chưa thanh toán' THEN

        RAISE EXCEPTION
            'No unpaid invoice is available';

    END IF;


    -- =====================================================
    -- 7. Tính số tiền còn phải trả
    -- =====================================================

    SELECT
        greatest(
            invoice."ThanhTien"
            -
            coalesce(
                sum(payment."SoTien")
                FILTER (
                    WHERE payment."TrangThai" =
                          'Thành công'
                ),
                0
            ),
            0
        )

    INTO amount_due

    FROM public."ThanhToan" AS payment

    WHERE payment."DonHangID" =
          p_donhangid;


    IF amount_due <= 0 THEN

        RAISE EXCEPTION
            'The invoice has no remaining balance';

    END IF;


    -- =====================================================
    -- 8. Tạo Payment
    -- =====================================================

    INSERT INTO public."ThanhToan" (

        "DonHangID",
        "PhuongThuc",
        "SoTien",
        "TrangThai",
        "IdempotencyKey",
        "GhiChu"

    )

    VALUES (

        p_donhangid,

        p_phuongthuc,

        amount_due,

        'Chờ thanh toán',

        p_idempotency_key,

        CASE
            WHEN p_phuongthuc = 'Chuyển khoản'
            THEN
                'Khách chọn chuyển khoản; chờ cửa hàng đối soát.'

            ELSE
                'Khách chọn tiền mặt; chờ cửa hàng thu tiền.'
        END

    )

    RETURNING "ThanhToanID"
    INTO payment_id;


    -- =====================================================
    -- 9. Trả kết quả
    -- =====================================================

    RETURN jsonb_build_object(

        'thanhtoanid',
        payment_id,

        'sotien',
        amount_due,

        'trangthai',
        'Chờ thanh toán',

        'phuongthuc',
        p_phuongthuc

    );

END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.save_customer_address(p_diachiid bigint, p_tennguoinhan text, p_sodienthoai text, p_diachi text, p_ghichu text, p_macdinh boolean)
 RETURNS bigint
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
	customer_id bigint := (SELECT private.current_customer_id());
	address_id bigint;
BEGIN
	IF (SELECT auth.uid()) IS NULL OR customer_id IS NULL THEN
		RAISE EXCEPTION 'An active customer account is required';
	END IF;
	IF nullif(btrim(p_diachi), '') IS NULL THEN
		RAISE EXCEPTION 'Address is required';
	END IF;

	IF p_diachiid IS NULL THEN
		INSERT INTO public.khachhang_diachi (
			khachhangid,
			tennguoinhan,
			sodienthoai,
			diachi,
			ghichu,
			macdinh
		) VALUES (
			customer_id,
			nullif(btrim(p_tennguoinhan), ''),
			nullif(btrim(p_sodienthoai), ''),
			btrim(p_diachi),
			nullif(btrim(p_ghichu), ''),
			false
		) RETURNING diachiid INTO address_id;
	ELSE
		UPDATE public.khachhang_diachi
		SET tennguoinhan = nullif(btrim(p_tennguoinhan), ''),
			sodienthoai = nullif(btrim(p_sodienthoai), ''),
			diachi = btrim(p_diachi),
			ghichu = nullif(btrim(p_ghichu), '')
		WHERE diachiid = p_diachiid
			AND khachhangid = customer_id
		RETURNING diachiid INTO address_id;

		IF address_id IS NULL THEN
			RAISE EXCEPTION 'Address not found';
		END IF;
	END IF;

	IF coalesce(p_macdinh, false) THEN
		UPDATE public.khachhang_diachi
		SET macdinh = false
		WHERE khachhangid = customer_id
			AND macdinh;

		UPDATE public.khachhang_diachi
		SET macdinh = true
		WHERE diachiid = address_id
			AND khachhangid = customer_id;
	END IF;

	RETURN address_id;
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.submit_laundry_booking_without_details(p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_id bigint := (SELECT private.current_customer_id());
  existing_booking record;
  booking_id bigint;
  booking_number text;
BEGIN
  IF customer_id IS NULL THEN
    RAISE EXCEPTION 'Authentication required';
  END IF;

  SELECT * INTO existing_booking
  FROM public."Booking"
  WHERE "IdempotencyKey" = p_idempotency_key
    AND "KhachHangID" = customer_id;

  IF FOUND THEN
    RETURN jsonb_build_object(
      'bookingid', existing_booking."BookingID",
      'mabooking', existing_booking."MaBooking",
      'trangthai', existing_booking."TrangThai",
      'thanhtien', 0,
      'itemcount', 0
    );
  END IF;

  booking_number := 'BK-' || to_char(clock_timestamp(), 'YYYYMMDDHH24MISS') ||
    '-' || substr(replace(gen_random_uuid()::text, '-', ''), 1, 8);

  INSERT INTO public."Booking" (
    "MaBooking", "KhachHangID", "HinhThucNhanDo", "DiaChiNhan",
    "NgayHen", "GioHen", "GhiChu", "TrangThai", "IdempotencyKey"
  ) VALUES (
    booking_number, customer_id, p_hinhthucnhando,
    nullif(btrim(p_diachinhan), ''), p_ngayhen, p_giohen,
    nullif(btrim(p_ghichu), ''), 'ChoTiepNhan', p_idempotency_key
  ) RETURNING "BookingID" INTO booking_id;

  RETURN jsonb_build_object(
    'bookingid', booking_id,
    'mabooking', booking_number,
    'trangthai', 'ChoTiepNhan',
    'thanhtien', 0,
    'itemcount', 0
  );
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.submit_laundry_order(p_banggiaid bigint, p_measurement numeric, p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$

DECLARE

    customer_id bigint :=
        (SELECT private.current_customer_id());

    existing_booking public."Booking"%ROWTYPE;

    price_row record;

    measurement numeric(10,2);
    line_total numeric(18,2);

    booking_id bigint;
    booking_number text;

    quantity numeric(10,2);
    weight_kg numeric(10,2);

    detail_id bigint;

BEGIN

    -- =====================================================
    -- 1. Kiểm tra khách hàng đăng nhập
    -- =====================================================

    IF (SELECT auth.uid()) IS NULL
       OR customer_id IS NULL THEN

        RAISE EXCEPTION
            'An active customer account is required';

    END IF;


    IF p_idempotency_key IS NULL THEN

        RAISE EXCEPTION
            'An idempotency key is required';

    END IF;


    -- =====================================================
    -- 2. Khóa theo idempotency key
    -- =====================================================

    PERFORM pg_catalog.pg_advisory_xact_lock(
        pg_catalog.hashtextextended(
            p_idempotency_key::text,
            0
        )
    );


    -- =====================================================
    -- 3. Kiểm tra Booking đã tạo trước đó
    -- =====================================================

    SELECT *
    INTO existing_booking

    FROM public."Booking" AS booking

    WHERE booking."IdempotencyKey" =
          p_idempotency_key;


    IF FOUND THEN

        IF existing_booking."KhachHangID" <> customer_id THEN

            RAISE EXCEPTION
                'Idempotency key is already in use';

        END IF;


        RETURN jsonb_build_object(

            'bookingid',
            existing_booking."BookingID",

            'mabooking',
            existing_booking."MaBooking",

            'trangthai',
            existing_booking."TrangThai",

            'thanhtien',
            COALESCE(
                (
                    SELECT SUM(cb."ThanhTien")

                    FROM public."ChiTietBooking" AS cb

                    WHERE cb."BookingID" =
                          existing_booking."BookingID"
                ),
                0
            )

        );

    END IF;


    -- =====================================================
    -- 4. Validate số lượng / khối lượng
    -- =====================================================

    IF p_measurement IS NULL
       OR p_measurement <= 0
       OR p_measurement > 99999999.99
       OR p_measurement <> round(p_measurement, 2) THEN

        RAISE EXCEPTION
            'Measurement must be positive and have at most two decimals';

    END IF;


    -- =====================================================
    -- 5. Validate hình thức nhận đồ
    -- =====================================================

    IF p_hinhthucnhando NOT IN (
        'Tại cửa hàng',
        'Tại nhà'
    ) THEN

        RAISE EXCEPTION
            'Unsupported pickup method';

    END IF;


    -- =====================================================
    -- 6. Validate lịch hẹn
    -- =====================================================

    IF p_ngayhen IS NULL
       OR p_ngayhen < current_date
       OR p_ngayhen > current_date + 30
       OR p_giohen IS NULL THEN

        RAISE EXCEPTION
            'Pickup appointment must be within the next 30 days';

    END IF;


    -- =====================================================
    -- 7. Nếu nhận tại nhà thì bắt buộc có địa chỉ
    -- =====================================================

    IF p_hinhthucnhando = 'Tại nhà'
       AND nullif(
            btrim(p_diachinhan),
            ''
       ) IS NULL THEN

        RAISE EXCEPTION
            'A pickup address is required';

    END IF;


    -- =====================================================
    -- 8. Lấy bảng giá đang hoạt động
    -- =====================================================

    SELECT
        price."DichVuID",
        price."LoaiDoGiatID",
        price."DonViTinhID",
        price."DonGia",
        unit."Ten",
        unit."KyHieu"

    INTO price_row

    FROM public."BangGia" AS price

    JOIN public."DichVu" AS service
        ON service."DichVuID" =
           price."DichVuID"

    JOIN public."LoaiDoGiat" AS item_type
        ON item_type."LoaiDoGiatID" =
           price."LoaiDoGiatID"

    JOIN public."DonViTinh" AS unit
        ON unit."DonViTinhID" =
           price."DonViTinhID"

    WHERE price."BangGiaID" =
          p_banggiaid

      AND price."TrangThai" = 'Hoạt động'

      AND price."NgayApDung" <= current_date

      AND (
          price."NgayKetThuc" IS NULL
          OR price."NgayKetThuc" >= current_date
      )

      AND service."TrangThai" = 'Hoạt động'

      AND item_type."TrangThai" = 'Hoạt động'

      AND unit."TrangThai" = 'Hoạt động'

    FOR SHARE OF price;


    IF NOT FOUND THEN

        RAISE EXCEPTION
            'The selected price is no longer available';

    END IF;


    -- =====================================================
    -- 9. Tính số lượng / khối lượng
    -- =====================================================

    measurement :=
        p_measurement::numeric(10,2);


    line_total :=
        round(
            price_row."DonGia" * measurement,
            2
        );


    IF lower(
        coalesce(
            price_row."KyHieu",
            price_row."Ten"
        )
    ) IN ('kg', 'kilogram') THEN

        weight_kg := measurement;
        quantity := NULL;

    ELSE

        quantity := measurement;
        weight_kg := NULL;

    END IF;


    -- =====================================================
    -- 10. Sinh mã Booking
    -- =====================================================

    booking_number :=
        'BK-' ||
        to_char(
            clock_timestamp(),
            'YYYYMMDDHH24MISS'
        ) ||
        '-' ||
        substr(
            replace(
                gen_random_uuid()::text,
                '-',
                ''
            ),
            1,
            8
        );


    -- =====================================================
    -- 11. Tạo Booking
    -- =====================================================

    INSERT INTO public."Booking" (

        "MaBooking",
        "KhachHangID",
        "HinhThucNhanDo",
        "DiaChiNhan",
        "NgayHen",
        "GioHen",
        "GhiChu",
        "TrangThai",
        "IdempotencyKey"

    )

    VALUES (

        booking_number,
        customer_id,
        p_hinhthucnhando,

        nullif(
            btrim(p_diachinhan),
            ''
        ),

        p_ngayhen,
        p_giohen,

        nullif(
            btrim(p_ghichu),
            ''
        ),

        'ChoTiepNhan',
        p_idempotency_key

    )

    RETURNING "BookingID"
    INTO booking_id;


    -- =====================================================
    -- 12. Tạo ChiTietBooking
    -- =====================================================

    INSERT INTO public."ChiTietBooking" (

        "BookingID",
        "DichVuID",
        "LoaiDoGiatID",
        "DonViTinhID",
        "SoLuong",
        "KhoiLuong",
        "DonGia",
        "ThanhTien",
        "GhiChu"

    )

    VALUES (

        booking_id,

        price_row."DichVuID",

        price_row."LoaiDoGiatID",

        price_row."DonViTinhID",

        quantity,

        weight_kg,

        price_row."DonGia",

        line_total,

        nullif(
            btrim(p_ghichu),
            ''
        )

    )

    RETURNING "ChiTietBookingID"
    INTO detail_id;


    -- =====================================================
    -- 13. Trả kết quả
    -- =====================================================

    RETURN jsonb_build_object(

        'bookingid',
        booking_id,

        'mabooking',
        booking_number,

        'chitietbookingid',
        detail_id,

        'trangthai',
        'ChoTiepNhan',

        'thanhtien',
        line_total

    );

END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.submit_laundry_order_cart(p_items jsonb, p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$

DECLARE

    customer_id bigint :=
        (SELECT private.current_customer_id());

    existing_booking record;
    booking_id bigint;
    booking_number text;
    detail_count integer := 0;
    total_amount numeric := 0;
    item jsonb;
    price_row record;
    measurement numeric;
    quantity numeric;
    weight_kg numeric;
    line_total numeric;
    detail_id integer;
    pickup_at timestamptz;

BEGIN

    -- =====================================================
    -- 1. Kiểm tra đăng nhập
    -- =====================================================

    IF customer_id IS NULL THEN

        RAISE EXCEPTION
            'Authentication required';

    END IF;


    -- =====================================================
    -- 2. Kiểm tra idempotency
    -- =====================================================

    SELECT * INTO existing_booking
    FROM public."Booking"
    WHERE "IdempotencyKey" = p_idempotency_key
      AND "KhachHangID" = customer_id;

    IF FOUND THEN

        RETURN jsonb_build_object(

            'bookingid',
            existing_booking."BookingID",

            'mabooking',
            existing_booking."MaBooking",

            'trangthai',
            existing_booking."TrangThai",

            'thanhtien',
            COALESCE(
                (
                    SELECT SUM(cb."ThanhTien")

                    FROM public."ChiTietBooking" AS cb

                    WHERE cb."BookingID" =
                          existing_booking."BookingID"
                ),
                0
            )

        );

    END IF;


    -- =====================================================
    -- 3. Validate items không rỗng
    -- =====================================================

    IF p_items IS NULL OR jsonb_array_length(p_items) = 0 THEN

        RAISE EXCEPTION
            'Cart must contain at least one item';

    END IF;


    -- =====================================================
    -- 4. Tạo booking number
    -- =====================================================

    booking_number :=
        'BK-' ||
        to_char(
            clock_timestamp(),
            'YYYYMMDDHH24MISS'
        ) ||
        '-' ||
        substr(
            replace(
                gen_random_uuid()::text,
                '-',
                ''
            ),
            1,
            8
        );


    -- =====================================================
    -- 5. Insert Booking
    -- =====================================================

    INSERT INTO public."Booking" (

        "MaBooking",
        "KhachHangID",
        "HinhThucNhanDo",
        "DiaChiNhan",
        "NgayHen",
        "GioHen",
        "GhiChu",
        "TrangThai",
        "IdempotencyKey"

    )

    VALUES (

        booking_number,
        customer_id,
        p_hinhthucnhando,

        nullif(
            btrim(p_diachinhan),
            ''
        ),

        p_ngayhen,
        p_giohen,

        nullif(
            btrim(p_ghichu),
            ''
        ),

        'ChoTiepNhan',
        p_idempotency_key

    )

    RETURNING "BookingID"
    INTO booking_id;


    -- =====================================================
    -- 6. Loop qua từng item và insert ChiTietBooking
    -- =====================================================

    FOR item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP

        -- Lấy thông tin giá
        SELECT
            bg."DonGia",
            bg."DichVuID",
            bg."LoaiDoGiatID",
            bg."DonViTinhID",
            lower(coalesce(dvt."KyHieu", dvt."TenDonViTinh")) AS unit_symbol

        INTO price_row

        FROM public."BangGia" AS bg

        INNER JOIN public."DichVu" AS dv
        ON bg."DichVuID" = dv."DichVuID"

        INNER JOIN public."LoaiDoGiat" AS ldg
        ON bg."LoaiDoGiatID" = ldg."LoaiDoGiatID"

        INNER JOIN public."DonViTinh" AS dvt
        ON bg."DonViTinhID" = dvt."DonViTinhID"

        WHERE bg."BangGiaID" = (item->>'banggiaid')::bigint
          AND bg."TrangThai" = 'Hoạt động'
          AND dv."TrangThai" = 'Hoạt động'
          AND ldg."TrangThai" = 'Hoạt động'
          AND dvt."TrangThai" = 'Hoạt động';


        IF NOT FOUND THEN

            RAISE EXCEPTION
                'Price ID % is no longer available',
                item->>'banggiaid';

        END IF;


        -- Validate measurement
        measurement := (item->>'measurement')::numeric(10,2);

        IF measurement IS NULL
           OR measurement <= 0
           OR measurement > 99999999.99
           OR measurement <> round(measurement, 2) THEN

            RAISE EXCEPTION
                'Invalid measurement for price ID %',
                item->>'banggiaid';

        END IF;


        -- Tính toán
        line_total := round(price_row."DonGia" * measurement, 2);

        IF price_row.unit_symbol IN ('kg', 'kilogram') THEN
            weight_kg := measurement;
            quantity := NULL;
        ELSE
            quantity := measurement;
            weight_kg := NULL;
        END IF;


        -- Insert ChiTietBooking
        INSERT INTO public."ChiTietBooking" (

            "BookingID",
            "DichVuID",
            "LoaiDoGiatID",
            "DonViTinhID",
            "SoLuong",
            "KhoiLuong",
            "DonGia",
            "ThanhTien",
            "GhiChu"

        )

        VALUES (

            booking_id,

            price_row."DichVuID",

            price_row."LoaiDoGiatID",

            price_row."DonViTinhID",

            quantity,

            weight_kg,

            price_row."DonGia",

            line_total,

            nullif(
                btrim(p_ghichu),
                ''
            )

        );


        detail_count := detail_count + 1;
        total_amount := total_amount + line_total;

    END LOOP;


    -- Giao nhận chỉ được tạo sau khi nhân viên xác nhận booking.
    -- Function confirm_laundry_booking đã tạo bản ghi GiaoNhan với DonHangID.

    -- =====================================================
    -- 7. Trả kết quả
    -- =====================================================

    RETURN jsonb_build_object(

        'bookingid',
        booking_id,

        'mabooking',
        booking_number,

        'trangthai',
        'ChoTiepNhan',

        'thanhtien',
        total_amount,

        'itemcount',
        detail_count

    );

END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.submit_laundry_order_cart_with_points(p_items jsonb, p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid, p_use_points boolean DEFAULT false)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_id bigint := (SELECT private.current_customer_id());
  booking_result jsonb;
  booking_id bigint;
  booking_row public."Booking"%ROWTYPE;
  available_points integer := 0;
  points_to_use integer := 0;
  points_discount numeric(18,2) := 0;
  total_amount numeric(18,2) := 0;
  remaining_points integer := 0;
BEGIN
  IF customer_id IS NULL THEN
    RAISE EXCEPTION 'Authentication required';
  END IF;

  booking_result := public.submit_laundry_order_cart(
    p_items,
    p_hinhthucnhando,
    p_diachinhan,
    p_ngayhen,
    p_giohen,
    p_ghichu,
    p_idempotency_key
  );
  booking_id := (booking_result->>'bookingid')::bigint;

  SELECT *
  INTO booking_row
  FROM public."Booking"
  WHERE "BookingID" = booking_id
    AND "KhachHangID" = customer_id
  FOR UPDATE;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Booking not found';
  END IF;

  SELECT COALESCE(SUM("ThanhTien"), 0)
  INTO total_amount
  FROM public."ChiTietBooking"
  WHERE "BookingID" = booking_id;

  IF NOT booking_row."DiemDaTru" THEN
    IF COALESCE(p_use_points, false) THEN
      SELECT "DiemHienTai"
      INTO available_points
      FROM public."DiemTichLuy"
      WHERE "KhachHangID" = customer_id
      FOR UPDATE;

      IF FOUND THEN
        points_to_use := LEAST(available_points::numeric, FLOOR(total_amount / 10))::integer;
        points_discount := points_to_use * 10;

        IF points_to_use > 0 THEN
          UPDATE public."DiemTichLuy"
          SET "DiemHienTai" = "DiemHienTai" - points_to_use,
              "NgayCapNhat" = now()
          WHERE "KhachHangID" = customer_id;
        END IF;
      END IF;
    END IF;

    UPDATE public."Booking"
    SET "DiemSuDung" = points_to_use,
        "TienGiamDoDiem" = points_discount,
        "DiemDaTru" = true
    WHERE "BookingID" = booking_id;
  END IF;

  SELECT COALESCE("DiemHienTai", 0)
  INTO remaining_points
  FROM public."DiemTichLuy"
  WHERE "KhachHangID" = customer_id;

  SELECT *
  INTO booking_row
  FROM public."Booking"
  WHERE "BookingID" = booking_id;

  RETURN booking_result || jsonb_build_object(
    'thanhtien', total_amount,
    'diemsudung', booking_row."DiemSuDung",
    'tiengiamdodiem', booking_row."TienGiamDoDiem",
    'thanhtoan', GREATEST(total_amount - booking_row."TienGiamDoDiem", 0),
    'diemconlai', COALESCE(remaining_points, 0)
  );
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.submit_order_review(p_donhangid bigint, p_sosao integer, p_binhluan text DEFAULT NULL::text)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_id bigint := (SELECT private.current_customer_id());
  review_id bigint;
BEGIN
  IF customer_id IS NULL THEN
    RAISE EXCEPTION 'Authentication required';
  END IF;

  IF p_sosao < 1 OR p_sosao > 5 THEN
    RAISE EXCEPTION 'Rating must be between 1 and 5';
  END IF;

  IF NOT EXISTS (
    SELECT 1
    FROM public."DonHang" AS order_record
    WHERE order_record."DonHangID" = p_donhangid
      AND order_record."KhachHangID" = customer_id
      AND order_record."TrangThai" IN ('Đã giao', 'Đã thanh toán')
  ) THEN
    RAISE EXCEPTION 'Only completed customer orders can be reviewed';
  END IF;

  INSERT INTO public."DanhGia" (
    "DonHangID", "KhachHangID", "SoSao", "BinhLuan", "NgayDanhGia", "TrangThai"
  ) VALUES (
    p_donhangid::integer,
    customer_id::integer,
    p_sosao,
    nullif(left(btrim(p_binhluan), 1000), ''),
    now(),
    'Hiển thị'
  )
  ON CONFLICT ("DonHangID") DO UPDATE
  SET "SoSao" = EXCLUDED."SoSao",
      "BinhLuan" = EXCLUDED."BinhLuan",
      "NgayDanhGia" = now(),
      "TrangThai" = 'Hiển thị'
  RETURNING "DanhGiaID" INTO review_id;

  RETURN jsonb_build_object('danhgiaid', review_id);
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.transition_laundry_order(p_donhangid bigint, p_trangthaimoi text, p_lydo text DEFAULT NULL::text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  order_record public."DonHang"%ROWTYPE;
  valid_transition boolean := false;
  current_employee bigint := (SELECT private.current_employee_id());
  current_account bigint := (
    SELECT "TaiKhoanID"
    FROM public."TaiKhoan"
    WHERE "UserAuthId" = (SELECT auth.uid())
    LIMIT 1
  );
  customer_account_id bigint;
BEGIN
  IF (SELECT auth.uid()) IS NULL
     OR NOT (SELECT private.is_staff())
     OR (
       current_employee IS NULL
       AND NOT (SELECT private.has_role('Quản lý'))
       AND NOT (SELECT private.has_role('Chủ cửa hàng'))
     ) THEN
    RAISE EXCEPTION 'An active staff account is required';
  END IF;

  SELECT *
  INTO order_record
  FROM public."DonHang"
  WHERE "DonHangID" = p_donhangid
  FOR UPDATE;

  IF NOT FOUND THEN
    RAISE EXCEPTION 'Order not found';
  END IF;

  valid_transition := CASE order_record."TrangThai"
    WHEN 'Chờ tiếp nhận' THEN p_trangthaimoi IN ('Đã tiếp nhận', 'Đã hủy')
    WHEN 'Đã tiếp nhận' THEN p_trangthaimoi IN ('Đang giặt', 'Đã hủy')
    WHEN 'Đang giặt' THEN p_trangthaimoi = 'Hoàn thành giặt'
    WHEN 'Hoàn thành giặt' THEN p_trangthaimoi IN ('Đang giao', 'Đã giao')
    WHEN 'Đang giao' THEN p_trangthaimoi = 'Đã giao'
    ELSE false
  END;

  IF NOT valid_transition THEN
    RAISE EXCEPTION 'Invalid order status transition: % -> %',
      order_record."TrangThai", p_trangthaimoi;
  END IF;

  IF p_trangthaimoi = 'Đã hủy' AND nullif(btrim(p_lydo), '') IS NULL THEN
    RAISE EXCEPTION 'A cancellation reason is required';
  END IF;

  UPDATE public."DonHang"
  SET "TrangThai" = p_trangthaimoi,
      "NhanVienID" = coalesce(current_employee, "NhanVienID"),
      "NgayCapNhat" = now()
  WHERE "DonHangID" = p_donhangid;

  INSERT INTO public."NhatKyHeThong" (
    "TaiKhoanID", "HanhDong", "BangDuLieu", "BanGhiID",
    "DuLieuCu", "DuLieuMoi", "LyDo", "ThoiGian"
  ) VALUES (
    current_account,
    'Thay đổi trạng thái đơn hàng',
    'DonHang',
    p_donhangid,
    jsonb_build_object('TrangThai', order_record."TrangThai"),
    jsonb_build_object('TrangThai', p_trangthaimoi),
    nullif(left(btrim(p_lydo), 500), ''),
    now()
  );

  customer_account_id := private.account_id_for_customer(order_record."KhachHangID");

  IF customer_account_id IS NOT NULL THEN
    INSERT INTO public."ThongBao" (
      "TaiKhoanID", "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc", "DonHangID", "LoaiThongBao"
    ) VALUES (
      customer_account_id,
      CASE WHEN p_trangthaimoi = 'Đã hủy'
        THEN 'Đơn hàng đã bị hủy'
        ELSE 'Cập nhật trạng thái đơn hàng'
      END,
      'Đơn hàng ' || order_record."MaDonHang" || ' đã chuyển sang trạng thái: ' || p_trangthaimoi || '.',
      now(),
      false,
      p_donhangid,
      CASE WHEN p_trangthaimoi = 'Đã hủy' THEN 'order_cancelled' ELSE 'status_update' END
    );
  END IF;
END;
$function$
;

-- Routine schema: public
CREATE OR REPLACE FUNCTION public.update_customer_profile(p_full_name text, p_email text DEFAULT NULL::text, p_phone text DEFAULT NULL::text, p_address text DEFAULT NULL::text)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  customer_id bigint := (SELECT private.current_customer_id());
  account_id bigint;
BEGIN
  IF customer_id IS NULL THEN
    RAISE EXCEPTION 'Authentication required';
  END IF;

  IF nullif(btrim(p_full_name), '') IS NULL THEN
    RAISE EXCEPTION 'Full name is required';
  END IF;

  SELECT "TaiKhoanID"
  INTO account_id
  FROM public."TaiKhoan"
  WHERE "KhachHangID" = customer_id
    AND "UserAuthId" = (SELECT auth.uid())
  LIMIT 1;

  UPDATE public."KhachHang"
  SET "HoTen" = left(btrim(p_full_name), 150),
      "Email" = nullif(left(btrim(p_email), 255), ''),
      "SoDienThoai" = nullif(left(btrim(p_phone), 20), ''),
      "DiaChi" = nullif(left(btrim(p_address), 255), '')
  WHERE "KhachHangID" = customer_id;

  UPDATE public."TaiKhoan"
  SET "Email" = nullif(left(btrim(p_email), 255), ''),
      "SoDienThoai" = nullif(left(btrim(p_phone), 20), '')
  WHERE "TaiKhoanID" = account_id;

  RETURN jsonb_build_object('khachhangid', customer_id, 'taikhoanid', account_id);
END;
$function$
;

CREATE OR REPLACE VIEW "public"."banggia" AS
 SELECT "BangGiaID" AS banggiaid,
    "DichVuID" AS dichvuid,
    "LoaiDoGiatID" AS loaidogiatid,
    "DonViTinhID" AS donvitinhid,
    "DonGia" AS dongia,
    "NgayApDung" AS ngayapdung,
    "NgayKetThuc" AS ngayketthuc,
    "TrangThai" AS trangthai
   FROM "BangGia";;

CREATE OR REPLACE VIEW "public"."danhgia" AS
 SELECT "DanhGiaID" AS danhgiaid,
    "DonHangID" AS donhangid,
    "KhachHangID" AS khachhangid,
    "SoSao" AS sosao,
    "BinhLuan" AS binhluan,
    "NgayDanhGia" AS ngaydanhgia,
    "TrangThai" AS trangthai
   FROM "DanhGia";;

CREATE OR REPLACE VIEW "public"."dichvu" AS
 SELECT "DichVuID" AS dichvuid,
    "LoaiDichVuID" AS loaidichvuid,
    "TenDichVu" AS tendichvu,
    "MoTa" AS mota,
    "ThoiGianDuKien" AS thoigiandukien,
    "TrangThai" AS trangthai,
    "NgayTao" AS ngaytao
   FROM "DichVu";;

CREATE OR REPLACE VIEW "public"."donvitinh" AS
 SELECT "DonViTinhID" AS donvitinhid,
    "TenDonViTinh" AS tendonvitinh,
    "KyHieu" AS kyhieu,
    "TrangThai" AS trangthai
   FROM "DonViTinh";;

CREATE OR REPLACE VIEW "public"."hoadon" AS
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

CREATE OR REPLACE VIEW "public"."khachhang" AS
 SELECT "KhachHangID" AS khachhangid,
    "HoTen" AS hoten,
    "SoDienThoai" AS sodienthoai,
    "Email" AS email,
    "DiaChi" AS diachi,
    "NgayTao" AS ngaytao,
    "TrangThai" AS trangthai
   FROM "KhachHang";;

CREATE OR REPLACE VIEW "public"."lichsuthaydoihoadon" AS
 SELECT "LichSuID" AS lichsuid,
    "HoaDonID" AS hoadonid,
    "TaiKhoanID" AS taikhoanid,
    "ThoiGian" AS thoigian,
    "TruongThayDoi" AS truongthaydoi,
    "GiaTriCu" AS giatricu,
    "GiaTriMoi" AS giatrimoi,
    "LyDo" AS lydo
   FROM "LichSuThayDoiHoaDon";;

CREATE OR REPLACE VIEW "public"."loaidichvu" AS
 SELECT "LoaiDichVuID" AS loaidichvuid,
    "TenLoaiDichVu" AS tenloaidichvu,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "LoaiDichVu";;

CREATE OR REPLACE VIEW "public"."loaidogiat" AS
 SELECT "LoaiDoGiatID" AS loaidogiatid,
    "TenLoaiDoGiat" AS tenloaidogiat,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "LoaiDoGiat";;

CREATE OR REPLACE VIEW "public"."nhanvien" AS
 SELECT "NhanVienID" AS nhanvienid,
    "HoTen" AS hoten,
    "SoDienThoai" AS sodienthoai,
    "Email" AS email,
    "DiaChi" AS diachi,
    "ChucDanh" AS chucdanh,
    "NgayVaoLam" AS ngayvaolam,
    "TrangThai" AS trangthai
   FROM "NhanVien";;

CREATE OR REPLACE VIEW "public"."quyen" AS
 SELECT "QuyenID" AS quyenid,
    "MaQuyen" AS maquyen,
    "TenQuyen" AS tenquyen,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "Quyen";;

CREATE OR REPLACE VIEW "public"."taikhoan" AS
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

CREATE OR REPLACE VIEW "public"."taikhoan_vaitro" AS
 SELECT "TaiKhoanID" AS taikhoanid,
    "VaiTroID" AS vaitroid
   FROM "TaiKhoan_VaiTro";;

CREATE OR REPLACE VIEW "public"."thanhtoan" AS
 SELECT "ThanhToanID" AS thanhtoanid,
    "DonHangID" AS donhangid,
    "SoTien" AS sotien,
    "PhuongThuc" AS phuongthuc,
    "MaGiaoDich" AS magiaodich,
    "ThoiGian" AS thoigian,
    "TrangThai" AS trangthai,
    "GhiChu" AS ghichu
   FROM "ThanhToan";;

CREATE OR REPLACE VIEW "public"."thongbao" AS
 SELECT "ThongBaoID" AS thongbaoid,
    "TaiKhoanID" AS taikhoanid,
    "DonHangID" AS donhangid,
    "LoaiThongBao" AS loaithongbao,
    "TieuDe" AS tieude,
    "NoiDung" AS noidung,
    "ThoiGianGui" AS thoigiangui,
    "DaDoc" AS dadoc
   FROM "ThongBao";;

CREATE OR REPLACE VIEW "public"."tinnhan" AS
 SELECT "TinNhanID" AS tinnhanid,
    "NguoiGuiID" AS nguoiguiid,
    "NguoiNhanID" AS nguoinhanid,
    "DonHangID" AS donhangid,
    "NoiDung" AS noidung,
    "ThoiGianGui" AS thoigiangui,
    "TrangThai" AS trangthai
   FROM "TinNhan";;

CREATE OR REPLACE VIEW "public"."vaitro" AS
 SELECT "VaiTroID" AS vaitroid,
    "TenVaiTro" AS tenvaitro,
    "MoTa" AS mota,
    "TrangThai" AS trangthai
   FROM "VaiTro";;

CREATE OR REPLACE VIEW "public"."vaitro_quyen" AS
 SELECT "VaiTroID" AS vaitroid,
    "QuyenID" AS quyenid
   FROM "VaiTro_Quyen";;

ALTER TABLE "public"."BangGia" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."Booking" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."ChiTietBooking" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."ChiTietDonHang" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."DanhGia" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."DanhMucLoaiDoGiat" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."DichVu" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."DiemTichLuy" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."DonHang" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."DonViTinh" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."GiaoNhan" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."HoaDon" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."KhachHang" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."KhuyenMai" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."LichSuThayDoiHoaDon" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."LoaiDichVu" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."LoaiDoGiat" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."NhanVien" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."NhatKyHeThong" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."Quyen" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."TaiKhoan" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."TaiKhoan_VaiTro" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."ThanhToan" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."ThongBao" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."TinNhan" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."VaiTro" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."VaiTro_Quyen" ENABLE ROW LEVEL SECURITY;
ALTER TABLE "public"."khachhang_diachi" ENABLE ROW LEVEL SECURITY;
CREATE POLICY "laundry_compat_catalog_price" ON "public"."BangGia" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING (((("TrangThai")::text = 'Hoạt động'::text) AND ("NgayApDung" <= CURRENT_DATE) AND (("NgayKetThuc" IS NULL) OR ("NgayKetThuc" >= CURRENT_DATE))));
CREATE POLICY "mobile_active_prices_read" ON "public"."BangGia" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING (((("TrangThai")::text = 'Hoạt động'::text) AND ("NgayApDung" <= CURRENT_DATE) AND (("NgayKetThuc" IS NULL) OR ("NgayKetThuc" >= CURRENT_DATE))));
CREATE POLICY "laundry_compat_booking_read" ON "public"."Booking" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) OR ( SELECT private.is_staff() AS is_staff)));
CREATE POLICY "laundry_compat_booking_detail_read" ON "public"."ChiTietBooking" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((EXISTS ( SELECT 1
   FROM "Booking" booking
  WHERE ((booking."BookingID" = "ChiTietBooking"."BookingID") AND ((booking."KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) OR ( SELECT private.is_staff() AS is_staff))))));
CREATE POLICY "laundry_compat_order_detail_read" ON "public"."ChiTietDonHang" AS PERMISSIVE FOR SELECT TO "authenticated" USING (( SELECT private.can_access_order(("ChiTietDonHang"."DonHangID")::bigint) AS can_access_order));
CREATE POLICY "laundry_compat_review_insert" ON "public"."DanhGia" AS PERMISSIVE FOR INSERT TO "authenticated" WITH CHECK ((("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) AND (EXISTS ( SELECT 1
   FROM "DonHang" order_record
  WHERE ((order_record."DonHangID" = "DanhGia"."DonHangID") AND (order_record."KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) AND ((order_record."TrangThai")::text = ANY ((ARRAY['Đã giao'::character varying, 'Đã thanh toán'::character varying])::text[])))))));
CREATE POLICY "laundry_compat_review_read" ON "public"."DanhGia" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) OR ( SELECT private.is_staff() AS is_staff)));
CREATE POLICY "laundry_compat_catalog_service" ON "public"."DichVu" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "mobile_active_services_read" ON "public"."DichVu" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "laundry_compat_loyalty_read" ON "public"."DiemTichLuy" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) OR ( SELECT private.is_staff() AS is_staff)));
CREATE POLICY "laundry_compat_order_read" ON "public"."DonHang" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) OR ( SELECT private.is_staff() AS is_staff)));
CREATE POLICY "laundry_compat_catalog_unit" ON "public"."DonViTinh" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "mobile_active_units_read" ON "public"."DonViTinh" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "laundry_compat_delivery_read" ON "public"."GiaoNhan" AS PERMISSIVE FOR SELECT TO "authenticated" USING (( SELECT private.can_access_order(("GiaoNhan"."DonHangID")::bigint) AS can_access_order));
CREATE POLICY "laundry_compat_invoice_read" ON "public"."HoaDon" AS PERMISSIVE FOR SELECT TO "authenticated" USING (( SELECT private.can_access_order(("HoaDon"."DonHangID")::bigint) AS can_access_order));
CREATE POLICY "laundry_compat_customer_read" ON "public"."KhachHang" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)) OR ( SELECT private.is_staff() AS is_staff)));
CREATE POLICY "laundry_compat_customer_update" ON "public"."KhachHang" AS PERMISSIVE FOR UPDATE TO "authenticated" USING (("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id))) WITH CHECK (("KhachHangID" = ( SELECT private.current_customer_id() AS current_customer_id)));
CREATE POLICY "laundry_compat_catalog_promotion" ON "public"."KhuyenMai" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING (((("TrangThai")::text = 'Hoạt động'::text) AND ("NgayBatDau" <= CURRENT_DATE) AND ("NgayKetThuc" >= CURRENT_DATE) AND (("SoLuongSuDung" IS NULL) OR ("SoLuongSuDung" > 0))));
CREATE POLICY "laundry_compat_invoice_history_read" ON "public"."LichSuThayDoiHoaDon" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((EXISTS ( SELECT 1
   FROM "HoaDon" invoice
  WHERE ((invoice."HoaDonID" = "LichSuThayDoiHoaDon"."HoaDonID") AND ( SELECT private.can_access_order((invoice."DonHangID")::bigint) AS can_access_order)))));
CREATE POLICY "laundry_compat_catalog_service_type" ON "public"."LoaiDichVu" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "laundry_compat_catalog_item_type" ON "public"."LoaiDoGiat" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "mobile_active_item_types_read" ON "public"."LoaiDoGiat" AS PERMISSIVE FOR SELECT TO "anon", "authenticated" USING ((("TrangThai")::text = 'Hoạt động'::text));
CREATE POLICY "laundry_compat_employee_read" ON "public"."NhanVien" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("NhanVienID" = ( SELECT private.current_employee_id() AS current_employee_id)) OR ( SELECT private.has_role('Quản lý'::text) AS has_role) OR ( SELECT private.has_role('Chủ cửa hàng'::text) AS has_role)));
CREATE POLICY "laundry_compat_permission_read" ON "public"."Quyen" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((( SELECT private.current_account_id() AS current_account_id) IS NOT NULL));
CREATE POLICY "laundry_compat_account_read" ON "public"."TaiKhoan" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("TaiKhoanID" = ( SELECT private.current_account_id() AS current_account_id)) OR ( SELECT private.has_role('Quản lý'::text) AS has_role) OR ( SELECT private.has_role('Chủ cửa hàng'::text) AS has_role)));
CREATE POLICY "laundry_compat_account_role_read" ON "public"."TaiKhoan_VaiTro" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("TaiKhoanID" = ( SELECT private.current_account_id() AS current_account_id)) OR ( SELECT private.has_role('Quản lý'::text) AS has_role) OR ( SELECT private.has_role('Chủ cửa hàng'::text) AS has_role)));
CREATE POLICY "laundry_compat_payment_read" ON "public"."ThanhToan" AS PERMISSIVE FOR SELECT TO "authenticated" USING (( SELECT private.can_access_order(("ThanhToan"."DonHangID")::bigint) AS can_access_order));
CREATE POLICY "laundry_compat_notification_delete" ON "public"."ThongBao" AS PERMISSIVE FOR DELETE TO "authenticated" USING (("TaiKhoanID" = ( SELECT private.current_account_id() AS current_account_id)));
CREATE POLICY "laundry_compat_notification_read" ON "public"."ThongBao" AS PERMISSIVE FOR SELECT TO "authenticated" USING (("TaiKhoanID" = ( SELECT private.current_account_id() AS current_account_id)));
CREATE POLICY "laundry_compat_notification_update" ON "public"."ThongBao" AS PERMISSIVE FOR UPDATE TO "authenticated" USING (("TaiKhoanID" = ( SELECT private.current_account_id() AS current_account_id))) WITH CHECK (("TaiKhoanID" = ( SELECT private.current_account_id() AS current_account_id)));
CREATE POLICY "laundry_compat_message_read" ON "public"."TinNhan" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((("NguoiGuiID" = ( SELECT private.current_account_id() AS current_account_id)) OR ("NguoiNhanID" = ( SELECT private.current_account_id() AS current_account_id)) OR (("DonHangID" IS NOT NULL) AND ( SELECT private.is_staff() AS is_staff) AND ( SELECT private.can_access_order(("TinNhan"."DonHangID")::bigint) AS can_access_order))));
CREATE POLICY "laundry_compat_role_read" ON "public"."VaiTro" AS PERMISSIVE FOR SELECT TO "authenticated" USING (((("TrangThai")::text = 'Hoạt động'::text) AND (( SELECT private.current_account_id() AS current_account_id) IS NOT NULL)));
CREATE POLICY "laundry_compat_role_permission_read" ON "public"."VaiTro_Quyen" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((( SELECT private.current_account_id() AS current_account_id) IS NOT NULL));
CREATE POLICY "laundry_compat_address_delete" ON "public"."khachhang_diachi" AS PERMISSIVE FOR DELETE TO "authenticated" USING ((khachhangid = ( SELECT private.current_customer_id() AS current_customer_id)));
CREATE POLICY "laundry_compat_address_read" ON "public"."khachhang_diachi" AS PERMISSIVE FOR SELECT TO "authenticated" USING ((khachhangid = ( SELECT private.current_customer_id() AS current_customer_id)));

CREATE TRIGGER notify_booking_created AFTER INSERT ON "Booking" FOR EACH ROW EXECUTE FUNCTION private.notify_booking_created();
CREATE TRIGGER laundry_compat_create_order_invoice AFTER INSERT ON "DonHang" FOR EACH ROW EXECUTE FUNCTION private.create_legacy_order_invoice();
CREATE TRIGGER laundry_compat_record_order_insert AFTER INSERT ON "DonHang" FOR EACH ROW EXECUTE FUNCTION private.record_legacy_order_status_change();
CREATE TRIGGER laundry_compat_record_order_status AFTER UPDATE OF "TrangThai" ON "DonHang" FOR EACH ROW EXECUTE FUNCTION private.record_legacy_order_status_change();
CREATE TRIGGER notify_customers_new_promotion AFTER INSERT ON "KhuyenMai" FOR EACH ROW EXECUTE FUNCTION private.notify_customers_new_promotion();
