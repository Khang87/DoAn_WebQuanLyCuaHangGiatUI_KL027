-- Supabase PostgreSQL live schema snapshot.
-- Public base-table columns, constraints and chat RPC definitions verified read-only on 2026-10-09.
-- Scope: 31 public base tables; views, indexes, policies and grants are not a full restore dump.
-- Schema only: no table rows. This local reference was not executed against Supabase.
--
-- Application contract verified against Live QLGiatUi on 2026-10-07:
--   Booking uses independent HinhThucNhanDo/DiaChiNhan and HinhThucTraDo/DiaChiTra.
--   HinhThucGiaoDo/DiaChiGiao remain used by submit_laundry_booking_with_delivery;
--   that RPC mirrors them to HinhThucTraDo/DiaChiTra. Retain all four existing columns.
--   Booking -> actual inspection -> DonHang (Da tiep nhan), with home-only delivery legs.
-- Laravel naming/reference notes for this snapshot:
--   User       -> public."TaiKhoan" ("AvatarURL" stores the public URL; avatar
--                                  files are stored outside PostgreSQL)
--   Customer   -> public."KhachHang" ("SoDienThoai" is unique; "Email" is not;
--                                     "AvatarUrl" is optional)
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
    "DiaChiNhan" text,
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
    "DiemDaTru" boolean DEFAULT false NOT NULL,
    "KhuyenMaiID" integer,
    "KhuyenMaiDaTru" boolean DEFAULT false NOT NULL,
    "PhuongThucThanhToan" character varying(30) DEFAULT 'Tiền mặt'::character varying NOT NULL,
    "HinhThucTraDo" text,
    "DiaChiTra" text,
    "HinhThucGiaoDo" character varying(30) DEFAULT 'Tại cửa hàng'::character varying NOT NULL,
    "DiaChiGiao" text,
    "PickupDistanceMeters" integer DEFAULT 0 NOT NULL,
    "PickupDeliveryFee" numeric(12,2) DEFAULT 0 NOT NULL,
    "DeliveryDistanceMeters" integer DEFAULT 0 NOT NULL,
    "DeliveryFee" numeric(12,2) DEFAULT 0 NOT NULL
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
    "TrangThai" character varying(30) DEFAULT 'Hoạt động'::character varying NOT NULL,
    "AvatarUrl" text
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
    "DaDoc" boolean DEFAULT false NOT NULL,
    "TinNhanID" integer,
    "BookingID" integer
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

CREATE TABLE IF NOT EXISTS "public"."delivery_fee_quote_attempts" (
    "id" uuid DEFAULT gen_random_uuid() NOT NULL,
    "auth_user_id" uuid NOT NULL,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL
);

CREATE TABLE IF NOT EXISTS "public"."delivery_fee_quotes" (
    "id" uuid DEFAULT gen_random_uuid() NOT NULL,
    "auth_user_id" uuid NOT NULL,
    "pickup_address_hash" text,
    "delivery_address_hash" text,
    "pickup_distance_meters" integer DEFAULT 0 NOT NULL,
    "delivery_distance_meters" integer DEFAULT 0 NOT NULL,
    "expires_at" timestamp with time zone NOT NULL,
    "consumed_booking_id" integer,
    "created_at" timestamp with time zone DEFAULT now() NOT NULL
);

ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_DichVuID_fkey" FOREIGN KEY ("DichVuID") REFERENCES "DichVu"("DichVuID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_DonGia_check" CHECK ("DonGia" >= 0::numeric);
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_DonViTinhID_fkey" FOREIGN KEY ("DonViTinhID") REFERENCES "DonViTinh"("DonViTinhID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_LoaiDoGiatID_fkey" FOREIGN KEY ("LoaiDoGiatID") REFERENCES "LoaiDoGiat"("LoaiDoGiatID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_TrangThai_check" CHECK ("TrangThai"::text = ANY (ARRAY['Hoạt động'::character varying, 'Hết hiệu lực'::character varying, 'Tạm ngưng'::character varying]::text[]));
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "BangGia_pkey" PRIMARY KEY ("BangGiaID");
ALTER TABLE ONLY "public"."BangGia" ADD CONSTRAINT "CK_BangGia_Ngay" CHECK ("NgayKetThuc" IS NULL OR "NgayKetThuc" >= "NgayApDung");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_DiemSuDung_check" CHECK ("DiemSuDung" >= 0);
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_HinhThucGiaoDo_check" CHECK ("HinhThucGiaoDo"::text = ANY (ARRAY['Tại cửa hàng'::character varying, 'Tại nhà'::character varying]::text[]));
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_HinhThucNhanDo_check" CHECK ("HinhThucNhanDo"::text = ANY (ARRAY['Tại cửa hàng'::character varying, 'Tại nhà'::character varying]::text[]));
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_KhachHangID_fkey" FOREIGN KEY ("KhachHangID") REFERENCES "KhachHang"("KhachHangID");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_KhuyenMaiID_fkey" FOREIGN KEY ("KhuyenMaiID") REFERENCES "KhuyenMai"("KhuyenMaiID");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_MaBooking_key" UNIQUE ("MaBooking");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_NhanVienID_fkey" FOREIGN KEY ("NhanVienID") REFERENCES "NhanVien"("NhanVienID") ON DELETE SET NULL;
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_NhanVienXacNhanID_fkey" FOREIGN KEY ("NhanVienXacNhanID") REFERENCES "NhanVien"("NhanVienID");
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_PhuongThucThanhToan_check" CHECK ("PhuongThucThanhToan"::text = ANY (ARRAY['Tiền mặt'::character varying, 'Chuyển khoản'::character varying]::text[]));
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
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_delivery_distance_nonnegative" CHECK ((("PickupDistanceMeters" >= 0) AND ("DeliveryDistanceMeters" >= 0)));
ALTER TABLE ONLY "public"."Booking" ADD CONSTRAINT "Booking_delivery_fee_nonnegative" CHECK ((("PickupDeliveryFee" >= (0)::numeric) AND ("DeliveryFee" >= (0)::numeric)));
ALTER TABLE ONLY "public"."ThongBao" ADD CONSTRAINT "ThongBao_BookingID_fkey" FOREIGN KEY ("BookingID") REFERENCES "Booking"("BookingID") ON DELETE SET NULL;
ALTER TABLE ONLY "public"."ThongBao" ADD CONSTRAINT "ThongBao_TinNhanID_fkey" FOREIGN KEY ("TinNhanID") REFERENCES "TinNhan"("TinNhanID") ON DELETE SET NULL;
ALTER TABLE ONLY "public"."delivery_fee_quote_attempts" ADD CONSTRAINT "delivery_fee_quote_attempts_pkey" PRIMARY KEY (id);
ALTER TABLE ONLY "public"."delivery_fee_quotes" ADD CONSTRAINT "delivery_fee_quotes_consumed_booking_id_fkey" FOREIGN KEY (consumed_booking_id) REFERENCES "Booking"("BookingID") ON DELETE SET NULL;
ALTER TABLE ONLY "public"."delivery_fee_quotes" ADD CONSTRAINT "delivery_fee_quotes_nonnegative_distances" CHECK (((pickup_distance_meters >= 0) AND (delivery_distance_meters >= 0)));
ALTER TABLE ONLY "public"."delivery_fee_quotes" ADD CONSTRAINT "delivery_fee_quotes_pkey" PRIMARY KEY (id);

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
	INSERT INTO public."HoaDon" (
		"MaHoaDon",
		"DonHangID",
		"TongTien",
		"GiamGia",
		"PhiGiaoHang",
		"ThanhTien",
		"TrangThai"
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
$function$;

CREATE OR REPLACE FUNCTION private.record_legacy_order_status_change()
 RETURNS trigger
 LANGUAGE plpgsql
 SET search_path TO ''
AS $function$
DECLARE points_awarded integer; action_name text; customer_balance integer; paid_total numeric; grand_total numeric;
BEGIN
 IF TG_OP='UPDATE' AND OLD."TrangThai" IS NOT DISTINCT FROM NEW."TrangThai" THEN RETURN NEW; END IF;
 INSERT INTO public."NhatKyHeThong"("TaiKhoanID","HanhDong","BangDuLieu","BanGhiID","DuLieuCu","DuLieuMoi","ThoiGian")
 VALUES(NULL,'Thay đổi trạng thái đơn hàng','DonHang',NEW."DonHangID",CASE WHEN TG_OP='UPDATE' THEN jsonb_build_object('TrangThai',OLD."TrangThai") END,jsonb_build_object('TrangThai',NEW."TrangThai"),now());
 IF NEW."TrangThai"='Đã hủy' THEN
  UPDATE public."HoaDon" SET "TrangThai"='Đã hủy' WHERE "DonHangID"=NEW."DonHangID";
  UPDATE public."GiaoNhan" SET "TrangThai"='Đã hủy' WHERE "DonHangID"=NEW."DonHangID";
 END IF;
 IF NEW."TrangThai"='Đã giao' THEN
  action_name:='Cộng điểm tích lũy đơn hàng';
  IF NOT EXISTS(SELECT 1 FROM public."NhatKyHeThong" WHERE "BangDuLieu"='DonHang' AND "BanGhiID"=NEW."DonHangID" AND "HanhDong"=action_name) THEN
   points_awarded:=floor(NEW."ThanhTien"/1000)::integer*100;
   INSERT INTO public."DiemTichLuy"("KhachHangID","DiemHienTai") VALUES(NEW."KhachHangID",0) ON CONFLICT("KhachHangID") DO NOTHING;
   SELECT "DiemHienTai" INTO customer_balance FROM public."DiemTichLuy" WHERE "KhachHangID"=NEW."KhachHangID" FOR UPDATE;
   UPDATE public."DiemTichLuy" SET "DiemHienTai"="DiemHienTai"+points_awarded,"NgayCapNhat"=now() WHERE "KhachHangID"=NEW."KhachHangID";
   INSERT INTO public."NhatKyHeThong"("HanhDong","BangDuLieu","BanGhiID","DuLieuCu","DuLieuMoi","ThoiGian")
   VALUES(action_name,'DonHang',NEW."DonHangID",jsonb_build_object('DiemHienTai',customer_balance),jsonb_build_object('DiemCong',points_awarded,'DiemHienTai',customer_balance+points_awarded,'ThanhTien',NEW."ThanhTien"),now());
  END IF;
  SELECT coalesce((SELECT "ThanhTien" FROM public."HoaDon" WHERE "DonHangID"=NEW."DonHangID" ORDER BY "HoaDonID" LIMIT 1),NEW."ThanhTien") INTO grand_total;
  SELECT coalesce(sum("SoTien"),0) INTO paid_total FROM public."ThanhToan" WHERE "DonHangID"=NEW."DonHangID" AND "TrangThai"='Thành công';
  IF grand_total>0 AND paid_total>=grand_total THEN
   UPDATE public."HoaDon" SET "TrangThai"='Đã thanh toán' WHERE "DonHangID"=NEW."DonHangID";
   UPDATE public."DonHang" SET "TrangThai"='Đã thanh toán',"NgayCapNhat"=now() WHERE "DonHangID"=NEW."DonHangID";
  END IF;
 ELSIF NEW."TrangThai"='Đã hủy' AND NEW."DiemSuDung">0 THEN
  action_name:='Hoàn điểm tích lũy đơn hàng';
  IF NOT EXISTS(SELECT 1 FROM public."NhatKyHeThong" WHERE "BangDuLieu"='DonHang' AND "BanGhiID"=NEW."DonHangID" AND "HanhDong"=action_name) THEN
   INSERT INTO public."DiemTichLuy"("KhachHangID","DiemHienTai") VALUES(NEW."KhachHangID",0) ON CONFLICT("KhachHangID") DO NOTHING;
   UPDATE public."DiemTichLuy" SET "DiemHienTai"="DiemHienTai"+NEW."DiemSuDung","NgayCapNhat"=now() WHERE "KhachHangID"=NEW."KhachHangID";
   INSERT INTO public."NhatKyHeThong"("HanhDong","BangDuLieu","BanGhiID","DuLieuMoi","ThoiGian") VALUES(action_name,'DonHang',NEW."DonHangID",jsonb_build_object('DiemHoan',NEW."DiemSuDung"),now());
  END IF;
 END IF;
 RETURN NEW;
END;
$function$;

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
  IF booking_row."TrangThai"='DaHuy' THEN RETURN; END IF;
  IF booking_row."TrangThai" <> 'ChoTiepNhan'
     OR EXISTS (SELECT 1 FROM public."DonHang" WHERE "BookingID" = p_bookingid) THEN
    RAISE EXCEPTION 'Only pending bookings can be canceled';
  END IF;
  IF booking_row."DiemDaTru" AND booking_row."DiemSuDung">0 THEN
    UPDATE public."DiemTichLuy" SET "DiemHienTai"="DiemHienTai"+booking_row."DiemSuDung","NgayCapNhat"=now() WHERE "KhachHangID"=customer_id;
    IF NOT FOUND THEN RAISE EXCEPTION 'Reserved points ledger is missing'; END IF;
  END IF;
  UPDATE public."Booking"
  SET "DiemDaTru"=false,"DiemSuDung"=0,"TienGiamDoDiem"=0,"KhuyenMaiDaTru"=false,
      "TrangThai" = 'DaHuy',
      "NgayCapNhat" = now()
  WHERE "BookingID" = p_bookingid;
  IF booking_row."KhuyenMaiDaTru"
     AND booking_row."KhuyenMaiID" IS NOT NULL THEN
    UPDATE public."KhuyenMai"
    SET "SoLuongSuDung" = "SoLuongSuDung" + 1
    WHERE "KhuyenMaiID" = booking_row."KhuyenMaiID"
      AND "SoLuongSuDung" IS NOT NULL;
  END IF;
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
    NULL::integer,
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
$function$;

CREATE OR REPLACE FUNCTION public.confirm_laundry_booking(p_bookingid bigint)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE booking_row public."Booking"%ROWTYPE; order_row public."DonHang"%ROWTYPE;
BEGIN
 IF auth.uid() IS NULL OR NOT coalesce(private.is_staff(),false) THEN RAISE EXCEPTION 'An active staff account is required'; END IF;
 SELECT * INTO booking_row FROM public."Booking" WHERE "BookingID"=p_bookingid FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Booking not found'; END IF;
 SELECT * INTO order_row FROM public."DonHang" WHERE "BookingID"=p_bookingid ORDER BY "DonHangID" LIMIT 1;
 IF FOUND THEN RETURN jsonb_build_object('donhangid',order_row."DonHangID",'madonhang',order_row."MaDonHang",'bookingid',p_bookingid,'trangthai',order_row."TrangThai",'tongtien',order_row."TongTien"); END IF;
 -- This existing RPC has no inspected items/condition argument. Never interpret
 -- a customer estimate (or an empty booking) as an inspection performed by staff.
 RAISE EXCEPTION 'Inspection required: use the Web inspection form to record actual measurements and item condition before creating an order' USING ERRCODE='22023';
END;
$function$;

CREATE OR REPLACE FUNCTION public.confirm_laundry_booking_without_details(p_bookingid bigint)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE booking_row public."Booking"%ROWTYPE; order_row public."DonHang"%ROWTYPE;
BEGIN
 IF auth.uid() IS NULL OR NOT coalesce(private.is_staff(),false) THEN RAISE EXCEPTION 'An active staff account is required'; END IF;
 SELECT * INTO booking_row FROM public."Booking" WHERE "BookingID"=p_bookingid FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Booking not found'; END IF;
 SELECT * INTO order_row FROM public."DonHang" WHERE "BookingID"=p_bookingid ORDER BY "DonHangID" LIMIT 1;
 IF FOUND THEN RETURN jsonb_build_object('donhangid',order_row."DonHangID",'madonhang',order_row."MaDonHang",'bookingid',p_bookingid,'trangthai',order_row."TrangThai",'tongtien',order_row."TongTien"); END IF;
 -- This existing RPC has no inspected items/condition argument. Never interpret
 -- a customer estimate (or an empty booking) as an inspection performed by staff.
 RAISE EXCEPTION 'Inspection required: use the Web inspection form to record actual measurements and item condition before creating an order' USING ERRCODE='22023';
END;
$function$;

CREATE OR REPLACE FUNCTION public.confirm_order_payment(p_thanhtoanid bigint, p_success boolean, p_ghichu text DEFAULT NULL::text)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE payment public."ThanhToan"%ROWTYPE; order_row public."DonHang"%ROWTYPE; invoice public."HoaDon"%ROWTYPE;
 order_id bigint; paid_total numeric; target_status text;
BEGIN
 IF auth.uid() IS NULL OR NOT coalesce(private.is_staff(),false) THEN RAISE EXCEPTION 'An active staff account is required'; END IF;
 IF p_success IS NULL THEN RAISE EXCEPTION 'A payment outcome is required'; END IF;
 SELECT "DonHangID" INTO order_id FROM public."ThanhToan" WHERE "ThanhToanID"=p_thanhtoanid;
 IF NOT FOUND THEN RAISE EXCEPTION 'Payment not found'; END IF;
 -- Every path locks order before payment/invoice: matches Web and request RPC.
 SELECT * INTO order_row FROM public."DonHang" WHERE "DonHangID"=order_id FOR UPDATE;
 SELECT * INTO payment FROM public."ThanhToan" WHERE "ThanhToanID"=p_thanhtoanid FOR UPDATE;
 IF payment."DonHangID"<>order_id THEN RAISE EXCEPTION 'Payment order changed'; END IF;
 target_status:=CASE WHEN p_success THEN 'Thành công' ELSE 'Thất bại' END;
 IF payment."TrangThai"=target_status THEN
  RETURN;
 END IF;
 IF payment."TrangThai"<>'Chờ thanh toán' THEN RAISE EXCEPTION 'Only pending payments can be confirmed'; END IF;
 IF order_row."TrangThai"='Đã hủy' THEN RAISE EXCEPTION 'Canceled orders cannot accept payments'; END IF;
 SELECT * INTO invoice FROM public."HoaDon" WHERE "DonHangID"=order_id ORDER BY "HoaDonID" LIMIT 1 FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Invoice not found'; END IF;
 SELECT coalesce(sum("SoTien"),0) INTO paid_total FROM public."ThanhToan" WHERE "DonHangID"=order_id AND "TrangThai"='Thành công';
 IF p_success AND (payment."SoTien"<=0 OR paid_total+payment."SoTien">invoice."ThanhTien") THEN RAISE EXCEPTION 'Payment exceeds the remaining invoice balance'; END IF;
 UPDATE public."ThanhToan" SET "TrangThai"=target_status,"ThoiGian"=now(),"GhiChu"=coalesce(nullif(btrim(p_ghichu),''),"GhiChu") WHERE "ThanhToanID"=p_thanhtoanid;
 IF p_success THEN
  paid_total:=paid_total+payment."SoTien";
  IF paid_total>=invoice."ThanhTien" THEN
   UPDATE public."HoaDon" SET "TrangThai"='Đã thanh toán' WHERE "HoaDonID"=invoice."HoaDonID";
   IF order_row."TrangThai"='Đã giao' THEN UPDATE public."DonHang" SET "TrangThai"='Đã thanh toán',"NgayCapNhat"=now() WHERE "DonHangID"=order_id; END IF;
  END IF;
 END IF;
 RETURN;
END;
$function$;

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
    WHERE "MaGiaoDich" = 'RPC-'||p_idempotency_key::text;
    IF FOUND THEN
        IF existing_payment."DonHangID"<>p_donhangid OR existing_payment."PhuongThuc" IS DISTINCT FROM p_phuongthuc THEN RAISE EXCEPTION 'Idempotency key is already in use'; END IF;
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
        "MaGiaoDich",
        "GhiChu"
    )
    VALUES (
        p_donhangid,
        p_phuongthuc,
        amount_due,
        'Chờ thanh toán',
        'RPC-'||p_idempotency_key::text,
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
$function$;

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
  IF p_idempotency_key IS NULL THEN RAISE EXCEPTION 'An idempotency key is required'; END IF;
  PERFORM pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(p_idempotency_key::text,0));
  IF p_hinhthucnhando IS NULL OR p_hinhthucnhando NOT IN ('Tại cửa hàng','Tại nhà') THEN RAISE EXCEPTION 'Unsupported pickup method'; END IF;
  IF p_hinhthucnhando='Tại nhà' AND nullif(btrim(p_diachinhan),'') IS NULL THEN RAISE EXCEPTION 'A pickup address is required'; END IF;
  IF p_ngayhen IS NULL OR p_ngayhen<current_date OR p_giohen IS NULL THEN RAISE EXCEPTION 'A current pickup appointment is required'; END IF;
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
  IF EXISTS(SELECT 1 FROM public."Booking" WHERE "IdempotencyKey"=p_idempotency_key) THEN RAISE EXCEPTION 'Idempotency key is already in use'; END IF;
  booking_number := 'BK-' || to_char(clock_timestamp(), 'YYYYMMDDHH24MISS') ||
    '-' || substr(replace(gen_random_uuid()::text, '-', ''), 1, 8);
  INSERT INTO public."Booking" (
    "MaBooking", "KhachHangID", "HinhThucNhanDo", "DiaChiNhan", "HinhThucTraDo",
    "NgayHen", "GioHen", "GhiChu", "TrangThai", "IdempotencyKey"
  ) VALUES (
    booking_number, customer_id, p_hinhthucnhando,
    nullif(btrim(p_diachinhan), ''), 'Tại cửa hàng', p_ngayhen, p_giohen,
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
$function$;

CREATE OR REPLACE FUNCTION public.submit_laundry_order(p_banggiaid bigint, p_measurement numeric, p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE result jsonb; detail_id bigint;
BEGIN
 result:=public.submit_laundry_order_cart(jsonb_build_array(jsonb_build_object('banggiaid',p_banggiaid,'measurement',p_measurement)),p_hinhthucnhando,p_diachinhan,p_ngayhen,p_giohen,p_ghichu,p_idempotency_key);
 SELECT min("ChiTietBookingID") INTO detail_id FROM public."ChiTietBooking" WHERE "BookingID"=(result->>'bookingid')::bigint;
 RETURN result||jsonb_build_object('chitietbookingid',detail_id);
END;
$function$;

CREATE OR REPLACE FUNCTION public.submit_laundry_order_cart(p_items jsonb, p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
 customer_id bigint := private.current_customer_id();
 booking_row public."Booking"%ROWTYPE;
 booking_id bigint;
 detail_count integer := 0;
 total_amount numeric := 0;
 item jsonb;
 price_row record;
 measurement numeric;
 quantity numeric;
 weight_kg numeric;
 line_total numeric;
BEGIN
 IF auth.uid() IS NULL OR customer_id IS NULL THEN RAISE EXCEPTION 'An active customer account is required'; END IF;
 IF p_idempotency_key IS NULL THEN RAISE EXCEPTION 'An idempotency key is required'; END IF;
 PERFORM pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(p_idempotency_key::text,0));
 SELECT * INTO booking_row FROM public."Booking" WHERE "IdempotencyKey"=p_idempotency_key;
 IF FOUND THEN
  IF booking_row."KhachHangID"<>customer_id THEN RAISE EXCEPTION 'Idempotency key is already in use'; END IF;
  SELECT count(*),coalesce(sum("ThanhTien"),0) INTO detail_count,total_amount FROM public."ChiTietBooking" WHERE "BookingID"=booking_row."BookingID";
  RETURN jsonb_build_object('bookingid',booking_row."BookingID",'mabooking',booking_row."MaBooking",'trangthai',booking_row."TrangThai",'thanhtien',total_amount,'itemcount',detail_count);
 END IF;
 IF p_items IS NULL OR jsonb_typeof(p_items)<>'array' OR jsonb_array_length(p_items)=0 THEN RAISE EXCEPTION 'Cart must contain at least one item'; END IF;
 IF p_hinhthucnhando IS NULL OR p_hinhthucnhando NOT IN ('Tại cửa hàng','Tại nhà') THEN RAISE EXCEPTION 'Unsupported pickup method'; END IF;
 IF p_hinhthucnhando='Tại nhà' AND nullif(btrim(p_diachinhan),'') IS NULL THEN RAISE EXCEPTION 'A pickup address is required'; END IF;
 IF p_ngayhen IS NULL OR p_ngayhen<current_date OR p_giohen IS NULL THEN RAISE EXCEPTION 'A current pickup appointment is required'; END IF;
 INSERT INTO public."Booking"("MaBooking","KhachHangID","HinhThucNhanDo","DiaChiNhan","HinhThucTraDo","NgayHen","GioHen","GhiChu","TrangThai","IdempotencyKey")
 VALUES('BK-'||substr(replace(gen_random_uuid()::text,'-',''),1,24),customer_id,p_hinhthucnhando,CASE WHEN p_hinhthucnhando='Tại nhà' THEN nullif(btrim(p_diachinhan),'') END,'Tại cửa hàng',p_ngayhen,p_giohen,nullif(btrim(p_ghichu),''),'ChoTiepNhan',p_idempotency_key)
 RETURNING "BookingID" INTO booking_id;
 UPDATE public."Booking" SET "MaBooking"='BK'||lpad(booking_id::text,greatest(4,length(booking_id::text)),'0') WHERE "BookingID"=booking_id;
 FOR item IN SELECT value FROM jsonb_array_elements(p_items) LOOP
  -- A client price ID identifies the tuple, never fixes a stale/future price.
  SELECT bg.*,lower(btrim(coalesce(u."KyHieu",u."TenDonViTinh"))) AS unit_symbol INTO price_row
  FROM public."BangGia" chosen JOIN public."BangGia" bg ON bg."DichVuID"=chosen."DichVuID" AND bg."LoaiDoGiatID"=chosen."LoaiDoGiatID" AND bg."DonViTinhID"=chosen."DonViTinhID"
  JOIN public."DonViTinh" u ON u."DonViTinhID"=bg."DonViTinhID"
  WHERE chosen."BangGiaID"=(item->>'banggiaid')::bigint AND bg."TrangThai"='Hoạt động'
   AND (bg."NgayApDung" IS NULL OR bg."NgayApDung"<=current_date) AND (bg."NgayKetThuc" IS NULL OR bg."NgayKetThuc">=current_date)
  ORDER BY bg."NgayApDung" DESC NULLS LAST,bg."BangGiaID" DESC LIMIT 1;
  IF NOT FOUND THEN RAISE EXCEPTION 'No effective price is available for the selected tuple'; END IF;
  measurement := (item->>'measurement')::numeric;
  IF measurement IS NULL OR measurement<=0 OR measurement>99999999.99 THEN RAISE EXCEPTION 'Invalid measurement'; END IF;
  IF price_row.unit_symbol IN ('kg','kgs','kilogram') THEN
   measurement:=round(measurement,2);
   IF measurement<=0 THEN RAISE EXCEPTION 'Invalid measurement'; END IF;
   weight_kg := measurement; quantity := NULL;
   line_total := round(price_row."DonGia"*greatest(measurement,3),0);
  ELSE
   IF measurement<>trunc(measurement) THEN RAISE EXCEPTION 'Piece quantity must be an integer'; END IF;
   quantity := measurement; weight_kg := NULL;
   line_total := round(price_row."DonGia"*measurement,0);
  END IF;
  INSERT INTO public."ChiTietBooking"("BookingID","DichVuID","LoaiDoGiatID","DonViTinhID","SoLuong","KhoiLuong","DonGia","ThanhTien","GhiChu")
  VALUES(booking_id,price_row."DichVuID",price_row."LoaiDoGiatID",price_row."DonViTinhID",quantity,weight_kg,price_row."DonGia",line_total,nullif(btrim(p_ghichu),''));
  detail_count:=detail_count+1;total_amount:=total_amount+line_total;
 END LOOP;
 SELECT * INTO booking_row FROM public."Booking" WHERE "BookingID"=booking_id;
 RETURN jsonb_build_object('bookingid',booking_id,'mabooking',booking_row."MaBooking",'trangthai','ChoTiepNhan','thanhtien',total_amount,'itemcount',detail_count);
END;
$function$;

CREATE OR REPLACE FUNCTION public.submit_laundry_order_cart_with_points(p_items jsonb, p_hinhthucnhando text, p_diachinhan text, p_ngayhen date, p_giohen time without time zone, p_ghichu text, p_idempotency_key uuid, p_use_points boolean DEFAULT false)
 RETURNS jsonb
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
 result jsonb; booking_row public."Booking"%ROWTYPE;
 customer_id bigint:=private.current_customer_id(); available_points integer; requested_points integer; total_amount numeric;
BEGIN
 result:=public.submit_laundry_order_cart(p_items,p_hinhthucnhando,p_diachinhan,p_ngayhen,p_giohen,p_ghichu,p_idempotency_key);
 SELECT * INTO booking_row FROM public."Booking" WHERE "BookingID"=(result->>'bookingid')::bigint AND "KhachHangID"=customer_id FOR UPDATE;
 SELECT coalesce("DiemHienTai",0) INTO available_points FROM public."DiemTichLuy" WHERE "KhachHangID"=customer_id;
 available_points:=coalesce(available_points,0);total_amount:=(result->>'thanhtien')::numeric;
 -- Preserve an old reservation on replay. New bookings store intent only.
 IF booking_row."TrangThai"='ChoTiepNhan' AND NOT booking_row."DiemDaTru" THEN
  requested_points:=CASE WHEN coalesce(p_use_points,false) THEN least(available_points::numeric,floor(total_amount))::integer ELSE 0 END;
  UPDATE public."Booking" SET "DiemSuDung"=requested_points,"TienGiamDoDiem"=requested_points,"DiemDaTru"=false WHERE "BookingID"=booking_row."BookingID" RETURNING * INTO booking_row;
 END IF;
 RETURN result||jsonb_build_object('diemsudung',booking_row."DiemSuDung",'tiengiamdodiem',booking_row."TienGiamDoDiem",'thanhtoan',greatest(total_amount-booking_row."TienGiamDoDiem",0),'diemconlai',available_points,'diemdadatru',booking_row."DiemDaTru");
END;
$function$;

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
  IF p_trangthaimoi=order_record."TrangThai" THEN RETURN; END IF;
  valid_transition := CASE order_record."TrangThai"
    WHEN 'Chờ tiếp nhận' THEN p_trangthaimoi = 'Đã hủy'
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
  IF p_trangthaimoi='Đã hủy' AND EXISTS (SELECT 1 FROM public."ThanhToan" WHERE "DonHangID"=p_donhangid AND "TrangThai"='Thành công') THEN RAISE EXCEPTION 'Collected payments must be refunded before cancellation'; END IF;
  UPDATE public."DonHang"
  SET "TrangThai" = p_trangthaimoi,
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
$function$;

-- Chat contract verified read-only from Live on 2026-10-09.
CREATE OR REPLACE FUNCTION public.get_staff_chat_inbox()
 RETURNS TABLE(customer_account_id integer, customer_name text, customer_avatar_url text, order_id integer, order_number text, last_message text, last_message_at timestamp without time zone, last_sender_account_id integer, unread_count bigint)
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  WITH conversations AS (
    SELECT DISTINCT ON (
      CASE WHEN sender."KhachHangID" IS NOT NULL THEN sender."TaiKhoanID" ELSE recipient."TaiKhoanID" END,
      m."DonHangID"
    )
      CASE WHEN sender."KhachHangID" IS NOT NULL THEN sender."TaiKhoanID" ELSE recipient."TaiKhoanID" END AS customer_account_id,
      m."DonHangID" AS order_id,
      m."NoiDung" AS last_message,
      m."ThoiGianGui" AS last_message_at,
      m."NguoiGuiID" AS last_sender_account_id
    FROM public."TinNhan" AS m
    JOIN public."TaiKhoan" AS sender ON sender."TaiKhoanID" = m."NguoiGuiID"
    JOIN public."TaiKhoan" AS recipient ON recipient."TaiKhoanID" = m."NguoiNhanID"
    WHERE sender."KhachHangID" IS NOT NULL OR recipient."KhachHangID" IS NOT NULL
    ORDER BY
      CASE WHEN sender."KhachHangID" IS NOT NULL THEN sender."TaiKhoanID" ELSE recipient."TaiKhoanID" END,
      m."DonHangID", m."ThoiGianGui" DESC, m."TinNhanID" DESC
  )
  SELECT c.customer_account_id,
    coalesce(k."HoTen", a."TenDangNhap", 'Khách hàng')::text,
    coalesce(k."AvatarUrl", a."AvatarURL")::text,
    c.order_id, d."MaDonHang"::text, c.last_message, c.last_message_at, c.last_sender_account_id,
    (
      SELECT count(*)
      FROM public."TinNhan" AS m
      JOIN public."TaiKhoan" AS sender ON sender."TaiKhoanID" = m."NguoiGuiID"
      WHERE sender."KhachHangID" IS NOT NULL
        AND m."NguoiGuiID" = c.customer_account_id
        AND m."DonHangID" IS NOT DISTINCT FROM c.order_id
        AND m."TrangThai" IN ('Đã gửi', 'Đã nhận')
    )
  FROM conversations AS c
  JOIN public."TaiKhoan" AS a ON a."TaiKhoanID" = c.customer_account_id
  LEFT JOIN public."KhachHang" AS k ON k."KhachHangID" = a."KhachHangID"
  LEFT JOIN public."DonHang" AS d ON d."DonHangID" = c.order_id
  WHERE auth.uid() IS NOT NULL AND private.is_messaging_staff()
  ORDER BY c.last_message_at DESC;
$function$;

-- Chat contract verified read-only from Live on 2026-10-09.
CREATE OR REPLACE FUNCTION public.get_support_chat_recipient()
 RETURNS integer
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO ''
AS $function$
  SELECT CASE
    WHEN auth.uid() IS NOT NULL
      AND private.current_account_id() IS NOT NULL
      AND NOT private.is_messaging_staff()
    THEN private.default_support_account_id()
    ELSE NULL
  END;
$function$;

-- Chat contract verified read-only from Live on 2026-10-09.
CREATE OR REPLACE FUNCTION public.mark_chat_thread_read(p_peer_account_id integer, p_order_id integer DEFAULT NULL::integer)
 RETURNS integer
 LANGUAGE sql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
  WITH customer_accounts AS (
    SELECT a."TaiKhoanID" FROM public."TaiKhoan" AS a
    WHERE a."KhachHangID" = (SELECT private.current_customer_id())
  ),
  updated AS (
    UPDATE public."TinNhan" AS m SET "TrangThai" = 'Đã đọc'
    WHERE m."DonHangID" IS NOT DISTINCT FROM p_order_id
      AND m."TrangThai" IN ('Đã gửi', 'Đã nhận')
      AND (
        (NOT private.is_messaging_staff()
          AND m."NguoiNhanID" IN (SELECT "TaiKhoanID" FROM customer_accounts)
          AND (p_order_id IS NULL OR (SELECT private.can_access_order(p_order_id::bigint)))
        )
        OR (private.is_messaging_staff()
          AND m."NguoiGuiID" = p_peer_account_id
          AND m."NguoiNhanID" IN (
            SELECT a."TaiKhoanID" FROM public."TaiKhoan" AS a
            WHERE a."NhanVienID" IS NOT NULL AND EXISTS (
              SELECT 1 FROM public."TaiKhoan_VaiTro" AS av
              JOIN public."VaiTro" AS r ON r."VaiTroID" = av."VaiTroID"
              WHERE av."TaiKhoanID" = a."TaiKhoanID"
                AND r."TrangThai" = 'Hoạt động'
                AND (r."TenVaiTro" IN ('Nhân viên','Chủ cửa hàng') OR r."TenVaiTro" LIKE 'Quản lý%')
            )
          )
          AND EXISTS (
            SELECT 1 FROM public."TaiKhoan" AS customer
            WHERE customer."TaiKhoanID" = p_peer_account_id
              AND customer."KhachHangID" IS NOT NULL
          ))
      )
    RETURNING m."TinNhanID"
  ),
  synced_notifications AS (
    UPDATE public."ThongBao" AS n SET "DaDoc" = true
    WHERE NOT private.is_messaging_staff()
      AND n."LoaiThongBao" = 'new_message'
      AND n."TaiKhoanID" IN (SELECT "TaiKhoanID" FROM customer_accounts)
      AND n."TinNhanID" IN (
        SELECT m."TinNhanID" FROM public."TinNhan" AS m
        WHERE m."DonHangID" IS NOT DISTINCT FROM p_order_id
          AND m."NguoiNhanID" IN (SELECT "TaiKhoanID" FROM customer_accounts)
          AND (m."TrangThai" = 'Đã đọc' OR m."TinNhanID" IN (SELECT "TinNhanID" FROM updated))
      )
    RETURNING n."ThongBaoID"
  )
  SELECT count(*)::integer FROM updated;
$function$;

-- Chat contract verified read-only from Live on 2026-10-09.
CREATE OR REPLACE FUNCTION public.notify_customer_of_store_message()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  recipient_customer_id integer;
  sender_is_staff boolean;
  recipient_is_staff boolean;
BEGIN
  SELECT a."KhachHangID" INTO recipient_customer_id
  FROM public."TaiKhoan" AS a
  WHERE a."TaiKhoanID" = NEW."NguoiNhanID" AND a."TrangThai" = 'Hoạt động';

  SELECT EXISTS (
    SELECT 1 FROM public."TaiKhoan" AS a
    JOIN public."TaiKhoan_VaiTro" AS av ON av."TaiKhoanID" = a."TaiKhoanID"
    JOIN public."VaiTro" AS r ON r."VaiTroID" = av."VaiTroID"
    WHERE a."TaiKhoanID" = NEW."NguoiGuiID"
      AND a."NhanVienID" IS NOT NULL AND a."TrangThai" = 'Hoạt động'
      AND r."TrangThai" = 'Hoạt động'
      AND (r."TenVaiTro" IN ('Nhân viên', 'Chủ cửa hàng') OR r."TenVaiTro" LIKE 'Quản lý%')
  ) INTO sender_is_staff;

  IF recipient_customer_id IS NOT NULL THEN
    INSERT INTO public."ThongBao" (
      "TaiKhoanID", "DonHangID", "TinNhanID", "LoaiThongBao",
      "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc"
    )
    SELECT customer_account."TaiKhoanID", NEW."DonHangID", NEW."TinNhanID",
      'new_message',
      CASE WHEN sender_is_staff THEN 'Tin nhắn mới từ cửa hàng' ELSE 'Tin nhắn mới' END,
      left(CASE WHEN sender_is_staff THEN 'Cửa hàng: ' ELSE 'Tin nhắn: ' END || NEW."NoiDung", 1000),
      timezone('utc', now())::timestamp, false
    FROM public."TaiKhoan" AS customer_account
    WHERE customer_account."KhachHangID" = recipient_customer_id
      AND customer_account."TrangThai" = 'Hoạt động'
      AND customer_account."TaiKhoanID" <> NEW."NguoiGuiID"
      AND (NEW."DonHangID" IS NULL OR EXISTS (
        SELECT 1 FROM public."DonHang" AS d
        WHERE d."DonHangID" = NEW."DonHangID" AND d."KhachHangID" = recipient_customer_id
      ))
      AND NOT EXISTS (
        SELECT 1 FROM public."ThongBao" AS existing
        WHERE existing."TaiKhoanID" = customer_account."TaiKhoanID"
          AND existing."TinNhanID" = NEW."TinNhanID"
          AND existing."LoaiThongBao" = 'new_message'
      );
  ELSE
    SELECT EXISTS (
      SELECT 1 FROM public."TaiKhoan" AS a
      JOIN public."TaiKhoan_VaiTro" AS av ON av."TaiKhoanID" = a."TaiKhoanID"
      JOIN public."VaiTro" AS r ON r."VaiTroID" = av."VaiTroID"
      WHERE a."TaiKhoanID" = NEW."NguoiNhanID"
        AND a."NhanVienID" IS NOT NULL AND a."TrangThai" = 'Hoạt động'
        AND r."TrangThai" = 'Hoạt động'
        AND (r."TenVaiTro" IN ('Nhân viên', 'Chủ cửa hàng') OR r."TenVaiTro" LIKE 'Quản lý%')
    ) INTO recipient_is_staff;
    IF recipient_is_staff THEN
      INSERT INTO public."ThongBao" (
        "TaiKhoanID", "DonHangID", "TinNhanID", "LoaiThongBao",
        "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc"
      ) VALUES (
        NEW."NguoiNhanID", NEW."DonHangID", NEW."TinNhanID", 'new_message',
        'Tin nhắn mới từ khách hàng', left('Khách hàng: ' || NEW."NoiDung", 1000),
        timezone('utc', now())::timestamp, false
      );
    END IF;
  END IF;
  RETURN NEW;
END
$function$;

-- Chat contract verified read-only from Live on 2026-10-09.
CREATE OR REPLACE FUNCTION public.send_chat_message(p_recipient_account_id integer, p_content text, p_order_id integer DEFAULT NULL::integer)
 RETURNS SETOF "TinNhan"
 LANGUAGE sql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
  INSERT INTO public."TinNhan"
    ("NguoiGuiID", "NguoiNhanID", "DonHangID", "NoiDung", "ThoiGianGui", "TrangThai")
  SELECT sender."TaiKhoanID", recipient."TaiKhoanID", p_order_id, btrim(p_content),
    timezone('utc', now())::timestamp, 'Đã gửi'
  FROM public."TaiKhoan" AS sender
  JOIN public."TaiKhoan" AS recipient ON recipient."TaiKhoanID" = p_recipient_account_id
  WHERE auth.uid() IS NOT NULL
    AND sender."TaiKhoanID" = private.current_account_id()
    AND sender."TrangThai" = 'Hoạt động'
    AND recipient."TrangThai" = 'Hoạt động'
    AND nullif(btrim(p_content), '') IS NOT NULL
    AND char_length(btrim(p_content)) <= 1000
    AND (
      (
        NOT private.is_messaging_staff()
        AND sender."KhachHangID" IS NOT NULL
        AND recipient."TaiKhoanID" = private.default_support_account_id()
        AND (
          p_order_id IS NULL
          OR (
            (SELECT private.can_access_order(p_order_id::bigint))
            AND EXISTS (
              SELECT 1 FROM public."DonHang" d
              WHERE d."DonHangID" = p_order_id
                AND d."KhachHangID" = sender."KhachHangID"
            )
          )
        )
      )
      OR (
        private.is_messaging_staff()
        AND recipient."KhachHangID" IS NOT NULL
        AND (
          p_order_id IS NULL
          OR EXISTS (
            SELECT 1 FROM public."DonHang" d
            WHERE d."DonHangID" = p_order_id
              AND d."KhachHangID" = recipient."KhachHangID"
          )
        )
      )
    )
  RETURNING *;
$function$;

-- Live send_chat_message limit aligned with NoiDung varchar(1000) on 2026-10-09.
-- Targeted migration: align_chat_message_limit_with_storage. Tests: ASCII/Unicode bounds and ACL.
-- Do not execute this reference snapshot as a production migration.
