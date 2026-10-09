-- Disposable database only. Auth helpers are explicit test doubles for the RPC boundary.
DO $$ BEGIN IF current_database()<>'laundry_rpc_test' THEN RAISE EXCEPTION 'Local tests only'; END IF; END $$;
BEGIN;
INSERT INTO public."KhachHang"("KhachHangID","HoTen") VALUES(1,'Chat customer'),(2,'Other customer');
INSERT INTO public."NhanVien"("NhanVienID","HoTen","SoDienThoai") VALUES(1,'Support employee','0000000000');
INSERT INTO public."TaiKhoan"("TaiKhoanID","TenDangNhap","KhachHangID","NhanVienID","UserAuthId") VALUES
(1,'customer',1,NULL,'00000000-0000-0000-0000-000000000001'),
(2,'staff',NULL,1,'00000000-0000-0000-0000-000000000002'),
(3,'other',2,NULL,'00000000-0000-0000-0000-000000000003');
INSERT INTO public."VaiTro"("VaiTroID","TenVaiTro") VALUES(1,'Nhân viên');
INSERT INTO public."TaiKhoan_VaiTro" VALUES(2,1);
CREATE FUNCTION private.current_account_id() RETURNS integer LANGUAGE sql STABLE AS $$ SELECT "TaiKhoanID" FROM public."TaiKhoan" WHERE "UserAuthId"=auth.uid() $$;
CREATE FUNCTION private.is_messaging_staff() RETURNS boolean LANGUAGE sql STABLE AS $$ SELECT coalesce(private.is_staff(),false) $$;
CREATE FUNCTION private.default_support_account_id() RETURNS integer LANGUAGE sql STABLE AS $$ SELECT 2 $$;
CREATE FUNCTION private.can_access_order(bigint) RETURNS boolean LANGUAGE sql STABLE AS $$ SELECT EXISTS(SELECT 1 FROM public."DonHang" WHERE "DonHangID"=$1 AND "KhachHangID"=private.current_customer_id()) $$;
\ir ../../database/sql/send-chat-message-length.sql
DO $$ BEGIN
PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000001',true);
ASSERT (SELECT count(*) FROM public.send_chat_message(2,repeat('A',1000)))=1,'1000 ASCII accepted';
ASSERT (SELECT count(*) FROM public.send_chat_message(2,repeat('ắ',1000)))=1,'1000 Unicode characters accepted';
ASSERT (SELECT count(*) FROM public.send_chat_message(2,repeat('A',1001)))=0,'1001 rejected without varchar overflow';
ASSERT (SELECT count(*) FROM public.send_chat_message(2,'   '))=0,'Blank rejected';
ASSERT (SELECT count(*) FROM public.send_chat_message(3,'Private customer message'))=0,'Customer cannot send to unrelated customer';
ASSERT (SELECT count(*) FROM public.send_chat_message(2,'Unknown order',999))=0,'Unknown order rejected';
PERFORM set_config('test.user_id','',true);
ASSERT (SELECT count(*) FROM public.send_chat_message(2,'Anonymous'))=0,'Anonymous cannot send';
PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000002',true);
ASSERT (SELECT count(*) FROM public.send_chat_message(1,'Store support reply'))=1,'Staff can reply before ordering';
ASSERT (SELECT count(*) FROM public."TinNhan")=3,'Only valid sends persisted';
END $$;
ROLLBACK;
