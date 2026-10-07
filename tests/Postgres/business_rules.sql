-- Local-only executable regression checks; fixtures.sql refuses Live database names.
DO $$ BEGIN IF current_database()<>'laundry_rpc_test' THEN RAISE EXCEPTION 'Local tests only'; END IF; END $$;
BEGIN;
INSERT INTO public."KhachHang"("KhachHangID","HoTen") VALUES(1,'Test customer'),(2,'Other customer');
INSERT INTO public."NhanVien"("NhanVienID","HoTen","SoDienThoai") VALUES(1,'Test employee','0000000000');
INSERT INTO public."TaiKhoan"("TaiKhoanID","TenDangNhap","KhachHangID","NhanVienID","UserAuthId") VALUES
 (1,'test-customer',1,NULL,'00000000-0000-0000-0000-000000000001'),
 (2,'test-staff',NULL,1,'00000000-0000-0000-0000-000000000002'),
 (3,'other-customer',2,NULL,'00000000-0000-0000-0000-000000000003');
INSERT INTO public."VaiTro"("VaiTroID","TenVaiTro") VALUES(1,'Nhân viên');
INSERT INTO public."TaiKhoan_VaiTro" VALUES(2,1);
INSERT INTO public."DiemTichLuy"("KhachHangID","DiemHienTai") VALUES(1,500);
INSERT INTO public."DichVu"("DichVuID","LoaiDichVuID","TenDichVu") VALUES(1,1,'Test service');
INSERT INTO public."LoaiDoGiat"("LoaiDoGiatID","TenLoaiDoGiat","DanhMucID") VALUES(1,'Test garment',1);
INSERT INTO public."DonViTinh"("DonViTinhID","TenDonViTinh","KyHieu") VALUES(1,'Kilogram','kg'),(2,'Piece','cai');
INSERT INTO public."BangGia"("BangGiaID","DichVuID","LoaiDoGiatID","DonViTinhID","DonGia","NgayApDung","NgayKetThuc") VALUES
 (1,1,1,1,1000,current_date-10,NULL),(2,1,1,1,2000,current_date,NULL),
 (3,1,1,1,99999,current_date+1,NULL),(4,1,1,1,99999,current_date-1,current_date-1),
 (5,1,1,2,10000,current_date,NULL),(6,1,1,1,2500,current_date,NULL);
DO $$
DECLARE result jsonb; repeated jsonb; b bigint; o bigint; payment_id bigint; balance integer; failed boolean;
BEGIN
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000001',true);
 result:=public.submit_laundry_order_cart_with_points('[{"banggiaid":1,"measurement":1}]','Tại cửa hàng',NULL,current_date+1,'10:00',NULL,'10000000-0000-0000-0000-000000000001',true);
 b:=(result->>'bookingid')::bigint;
 ASSERT (result->>'thanhtien')::numeric=7500,'Newest effective price, tie ID, minimum 3 kg';
 ASSERT (SELECT "DiemHienTai" FROM public."DiemTichLuy" WHERE "KhachHangID"=1)=500,'Booking cannot deduct points';
 ASSERT (SELECT NOT "DiemDaTru" AND "DiemSuDung"=500 FROM public."Booking" WHERE "BookingID"=b),'Booking contains intent only';
 repeated:=public.submit_laundry_order_cart_with_points('[{"banggiaid":1,"measurement":1}]','Tại cửa hàng',NULL,current_date+1,'10:00',NULL,'10000000-0000-0000-0000-000000000001',true);
 ASSERT repeated->>'bookingid'=result->>'bookingid','Replay must reuse booking';
 ASSERT (SELECT count(*) FROM public."ChiTietBooking" WHERE "BookingID"=b)=1,'Replay must not duplicate lines';
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000003',true);
 failed:=false;BEGIN
  PERFORM public.submit_laundry_order_cart('[{"banggiaid":1,"measurement":1}]','Tại cửa hàng',NULL,current_date+1,'10:00',NULL,'10000000-0000-0000-0000-000000000001');
 EXCEPTION WHEN OTHERS THEN IF SQLERRM='Idempotency key is already in use' THEN failed:=true; ELSE RAISE; END IF; END;
 ASSERT failed,'Cross-customer idempotency must be rejected';
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000002',true);
 failed:=false;BEGIN PERFORM public.confirm_laundry_booking(b);EXCEPTION WHEN SQLSTATE '22023' THEN failed:=true;END;
 ASSERT failed AND NOT EXISTS(SELECT 1 FROM public."DonHang" WHERE "BookingID"=b),'Estimate cannot create inspected order';
 failed:=false;BEGIN PERFORM public.confirm_laundry_booking_without_details(b);EXCEPTION WHEN SQLSTATE '22023' THEN failed:=true;END;
 ASSERT failed,'No-details confirmation cannot bypass inspection';
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000001',true);
 failed:=false;BEGIN
  PERFORM public.submit_laundry_order_cart('[{"banggiaid":5,"measurement":1.5}]','Tại cửa hàng',NULL,current_date+1,'10:00',NULL,'10000000-0000-0000-0000-000000000004');
 EXCEPTION WHEN OTHERS THEN IF SQLERRM='Piece quantity must be an integer' THEN failed:=true; ELSE RAISE; END IF; END;
 ASSERT failed,'Piece quantities must be whole numbers';
 -- Legacy reservation refund is exact and idempotent.
 UPDATE public."DiemTichLuy" SET "DiemHienTai"=400 WHERE "KhachHangID"=1;
 UPDATE public."Booking" SET "DiemDaTru"=true,"DiemSuDung"=100,"TienGiamDoDiem"=100 WHERE "BookingID"=b;
 PERFORM public.cancel_laundry_booking(b);PERFORM public.cancel_laundry_booking(b);
 ASSERT (SELECT "DiemHienTai" FROM public."DiemTichLuy" WHERE "KhachHangID"=1)=500,'Legacy cancellation refund exactly once';
 -- Simulate Web's inspected order; existing database triggers run for both clients.
 INSERT INTO public."DonHang"("MaDonHang","KhachHangID","NhanVienID","TrangThai","TongTien","ThanhTien") VALUES('TEST-ORDER',1,1,'Đã tiếp nhận',10000,10000) RETURNING "DonHangID" INTO o;
 ASSERT EXISTS(SELECT 1 FROM public."HoaDon" WHERE "DonHangID"=o),'Invoice trigger must use real HoaDon table';
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000002',true);
 failed:=false;BEGIN PERFORM public.transition_laundry_order(o,'Đã giao',NULL);EXCEPTION WHEN OTHERS THEN IF SQLERRM LIKE 'Invalid order status transition:%' THEN failed:=true; ELSE RAISE; END IF;END;
 ASSERT failed,'Cannot skip washing';
 PERFORM public.transition_laundry_order(o,'Đang giặt',NULL);
 PERFORM public.transition_laundry_order(o,'Hoàn thành giặt',NULL);
 PERFORM public.transition_laundry_order(o,'Đã giao',NULL);
 PERFORM public.transition_laundry_order(o,'Đã giao',NULL);
 ASSERT (SELECT "DiemHienTai" FROM public."DiemTichLuy" WHERE "KhachHangID"=1)=1500,'Completion awards 100 points per 1000 VND exactly once';
 ASSERT (SELECT count(*) FROM public."NhatKyHeThong" WHERE "BanGhiID"=o AND "HanhDong"='Cộng điểm tích lũy đơn hàng')=1,'Shared award marker';
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000001',true);
 result:=public.request_order_payment(o,'Tiền mặt','20000000-0000-0000-0000-000000000001');payment_id:=(result->>'thanhtoanid')::bigint;
 repeated:=public.request_order_payment(o,'Tiền mặt','20000000-0000-0000-0000-000000000001');
 ASSERT (repeated->>'thanhtoanid')::bigint=payment_id,'Payment request replay uses MaGiaoDich without a new column';
 PERFORM set_config('test.user_id','00000000-0000-0000-0000-000000000002',true);
 PERFORM public.confirm_order_payment(payment_id,true,NULL);PERFORM public.confirm_order_payment(payment_id,true,NULL);
 ASSERT (SELECT "TrangThai" FROM public."DonHang" WHERE "DonHangID"=o)='Đã thanh toán','Delivered and paid becomes settled';
 ASSERT (SELECT sum("SoTien") FROM public."ThanhToan" WHERE "DonHangID"=o AND "TrangThai"='Thành công')=10000,'Repeated confirmation cannot collect twice';
 ASSERT (SELECT "DiemHienTai" FROM public."DiemTichLuy" WHERE "KhachHangID"=1)=1500,'Settlement cannot award completion twice';
 -- An inspected order canceled before washing returns redeemed points only once.
 UPDATE public."DiemTichLuy" SET "DiemHienTai"="DiemHienTai"-100 WHERE "KhachHangID"=1;
 INSERT INTO public."DonHang"("MaDonHang","KhachHangID","TrangThai","TongTien","DiemSuDung","TienGiamDoDiem","ThanhTien") VALUES('TEST-CANCEL',1,'Đã tiếp nhận',10000,100,100,9900) RETURNING "DonHangID" INTO o;
 PERFORM public.transition_laundry_order(o,'Đã hủy','Test cancellation');PERFORM public.transition_laundry_order(o,'Đã hủy','Replay');
 ASSERT (SELECT "DiemHienTai" FROM public."DiemTichLuy" WHERE "KhachHangID"=1)=1500,'Order cancellation refund exactly once';
 ASSERT (SELECT "TrangThai" FROM public."HoaDon" WHERE "DonHangID"=o)='Đã hủy','Cancellation closes the invoice';
 RAISE NOTICE 'RPC business-rule regression checks passed';
END $$;
ROLLBACK;
