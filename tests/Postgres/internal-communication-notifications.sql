-- Run only against the disposable schema restore; never against live customer data.
BEGIN;

INSERT INTO public."KhachHang" ("KhachHangID", "HoTen") VALUES (9901, 'Test customer');
INSERT INTO public."NhanVien" ("NhanVienID", "HoTen", "SoDienThoai") VALUES
  (9902, 'Custom group', '090009902'), (9903, 'Existing group', '090009903'),
  (9904, 'Mixed group', '090009904'), (9906, 'Inactive group', '090009906');
INSERT INTO public."TaiKhoan" ("TaiKhoanID", "TenDangNhap", "KhachHangID", "NhanVienID", "TrangThai") VALUES
  (9901, 'test-customer', 9901, NULL, 'Hoạt động'),
  (9902, 'test-custom-internal', NULL, 9902, 'Hoạt động'),
  (9903, 'test-existing-internal', NULL, 9903, 'Hoạt động'),
  (9904, 'test-mixed', NULL, 9904, 'Hoạt động'),
  (9906, 'test-inactive', NULL, 9906, 'Khóa');
INSERT INTO public."VaiTro" ("VaiTroID", "TenVaiTro", "TrangThai") VALUES
  (9901, 'Khách hàng', 'Hoạt động'),
  (9902, 'Nhóm mới bất kỳ', 'Hoạt động'),
  (9903, 'Nhân viên', 'Hoạt động'),
  (9904, 'Client', 'Hoạt động');
INSERT INTO public."TaiKhoan_VaiTro" ("TaiKhoanID", "VaiTroID") VALUES
  (9901, 9901), (9902, 9902), (9903, 9903), (9904, 9902), (9904, 9904), (9906, 9902);

SELECT public.notify_staff_new_booking(9901, 'BK-INTERNAL-9901', 'Customer');
DO $$ BEGIN
  ASSERT (SELECT array_agg("TaiKhoanID" ORDER BY "TaiKhoanID") FROM public."ThongBao"
    WHERE "LoaiThongBao" = 'new_booking') = ARRAY[9902, 9903],
    'New booking must notify all active internal roles, excluding customer and mixed accounts';
END $$;

INSERT INTO public."TinNhan" ("NguoiGuiID", "NguoiNhanID", "NoiDung")
VALUES (9901, 9902, 'Customer asks a question');
DO $$ BEGIN
  ASSERT (SELECT array_agg("TaiKhoanID" ORDER BY "TaiKhoanID") FROM public."ThongBao"
    WHERE "LoaiThongBao" = 'new_message') = ARRAY[9902, 9903],
    'Customer message must notify every active internal role';
END $$;

INSERT INTO public."TinNhan" ("NguoiGuiID", "NguoiNhanID", "NoiDung")
VALUES (9902, 9901, 'The store replies');
DO $$ BEGIN
  ASSERT (SELECT "TieuDe" FROM public."ThongBao"
    WHERE "LoaiThongBao" = 'new_message' AND "TaiKhoanID" = 9901) = 'Tin nhắn mới từ cửa hàng',
    'Custom internal sender must be identified as the store to the mobile customer';
END $$;

ROLLBACK;
