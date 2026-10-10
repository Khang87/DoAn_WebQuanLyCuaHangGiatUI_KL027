DO $$ BEGIN IF current_database()<>'laundry_rpc_test' THEN RAISE EXCEPTION 'Local tests only'; END IF; END $$;
BEGIN;
INSERT INTO public."TaiKhoan"("TaiKhoanID","TenDangNhap","KhachHangID","NhanVienID") VALUES
(9001,'Customer A',9001,NULL),(9002,'Customer B',9002,NULL),(9003,'Staff',NULL,9001);
INSERT INTO private.web_message_channels VALUES
('order:9001',1,'web-message:order-a',now()+interval '5 minutes'),
('order:9002',1,'web-message:order-b',now()+interval '5 minutes'),
('order:9001',0,'web-message:expired',now()-interval '1 second'),
('support:9001',1,'web-message:support-a',now()+interval '5 minutes'),
('support:9002',1,'web-message:support-b',now()+interval '5 minutes');
DO $$ DECLARE id bigint; BEGIN
INSERT INTO public."TinNhan"("NguoiGuiID","NguoiNhanID","DonHangID","NoiDung")
VALUES(9001,9003,9001,'Secret order message') RETURNING "TinNhanID" INTO id;
ASSERT (SELECT count(*) FROM realtime.test_signals)=1,'Only exact order topic, not expired/other/support';
ASSERT EXISTS(SELECT 1 FROM realtime.test_signals WHERE topic='web-message:order-a' AND payload='{}'::jsonb AND event='changed' AND NOT private),'Public timing-only signal';
TRUNCATE realtime.test_signals;
UPDATE public."TinNhan" SET "DonHangID"=9002 WHERE "TinNhanID"=id;
ASSERT (SELECT count(*) FROM realtime.test_signals)=2,'Moves invalidate old and new conversation';
TRUNCATE realtime.test_signals;
DELETE FROM public."TinNhan" WHERE "TinNhanID"=id;
ASSERT EXISTS(SELECT 1 FROM realtime.test_signals WHERE topic='web-message:order-b'),'Deletes invalidate old scope';
TRUNCATE realtime.test_signals;
INSERT INTO public."TinNhan"("NguoiGuiID","NguoiNhanID","NoiDung") VALUES(9001,9003,'Support question');
INSERT INTO public."TinNhan"("NguoiGuiID","NguoiNhanID","NoiDung") VALUES(9003,9001,'Support reply');
ASSERT (SELECT count(*) FROM realtime.test_signals)=2,'Both support directions invalidate selected customer';
ASSERT NOT EXISTS(SELECT 1 FROM realtime.test_signals WHERE topic<>'web-message:support-a'),'No other customer/order signals';
TRUNCATE realtime.test_signals;
INSERT INTO public."TinNhan"("NguoiGuiID","NguoiNhanID","NoiDung") VALUES(9001,9002,'Unrelated customer message');
ASSERT (SELECT count(*) FROM realtime.test_signals)=0,'Customer to customer does not match store support';
PERFORM set_config('test.broadcast_failure','true',true);
INSERT INTO public."TinNhan"("NguoiGuiID","NguoiNhanID","DonHangID","NoiDung") VALUES(9001,9003,9001,'Survives transport failure');
ASSERT EXISTS(SELECT 1 FROM public."TinNhan" WHERE "NoiDung"='Survives transport failure'),'Realtime failure cannot roll back TinNhan';
PERFORM set_config('test.broadcast_failure','false',true);
PERFORM set_config('test.broadcast_cancel','true',true);
INSERT INTO public."TinNhan"("NguoiGuiID","NguoiNhanID","DonHangID","NoiDung") VALUES(9001,9003,9001,'Survives transport timeout');
ASSERT EXISTS(SELECT 1 FROM public."TinNhan" WHERE "NoiDung"='Survives transport timeout'),'Broadcast timeout cannot roll back TinNhan';
ASSERT (SELECT relrowsecurity FROM pg_class WHERE oid='private.web_message_channels'::regclass),'Registry RLS enabled';
ASSERT NOT EXISTS(SELECT 1 FROM information_schema.role_table_grants WHERE table_schema='private' AND table_name='web_message_channels' AND grantee='PUBLIC'),'Registry not public';
END $$;
ROLLBACK;
