BEGIN;
SET LOCAL lock_timeout = '5s';
SET LOCAL statement_timeout = '30s';
DO $guard$ BEGIN
 IF to_regprocedure('public.submit_laundry_order_cart(jsonb,text,text,date,time without time zone,text,uuid)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.submit_laundry_order(bigint,numeric,text,text,date,time without time zone,text,uuid)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.submit_laundry_order_cart_with_points(jsonb,text,text,date,time without time zone,text,uuid,boolean)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.confirm_laundry_booking(bigint)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.confirm_laundry_booking_without_details(bigint)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.cancel_laundry_booking(bigint)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('private.create_legacy_order_invoice()') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.confirm_order_payment(bigint,boolean,text)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.transition_laundry_order(bigint,text,text)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('private.record_legacy_order_status_change()') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('private.apply_booking_promotion_to_order()') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.submit_laundry_booking_without_details(text,text,date,time without time zone,text,uuid)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
 IF to_regprocedure('public.request_order_payment(bigint,text,uuid)') IS NULL THEN RAISE EXCEPTION 'Expected existing RPC is missing'; END IF;
END $guard$;

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

CREATE OR REPLACE FUNCTION private.apply_booking_promotion_to_order()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  booking_row public."Booking"%ROWTYPE;
  promotion public."KhuyenMai"%ROWTYPE;
  discount_amount numeric(18,2);
BEGIN
  IF TG_OP='UPDATE' AND OLD."TongTien" IS NOT DISTINCT FROM NEW."TongTien" AND OLD."TienGiamDoDiem" IS NOT DISTINCT FROM NEW."TienGiamDoDiem" AND OLD."PhiGiaoHang" IS NOT DISTINCT FROM NEW."PhiGiaoHang" AND OLD."KhuyenMaiID" IS NOT DISTINCT FROM NEW."KhuyenMaiID" THEN RETURN NEW; END IF;
  IF NEW."BookingID" IS NULL THEN
    RETURN NEW;
  END IF;
  SELECT *
  INTO booking_row
  FROM public."Booking"
  WHERE "BookingID" = NEW."BookingID";
  IF NOT FOUND OR booking_row."KhuyenMaiID" IS NULL THEN
    RETURN NEW;
  END IF;
  SELECT *
  INTO promotion
  FROM public."KhuyenMai"
  WHERE "KhuyenMaiID" = booking_row."KhuyenMaiID";
  IF NOT FOUND THEN
    RETURN NEW;
  END IF;
  IF promotion."TrangThai"<>'Hoạt động' OR promotion."NgayBatDau">current_date OR promotion."NgayKetThuc"<current_date
     OR (promotion."GiaTriDonToiThieu" IS NOT NULL AND NEW."TongTien"<promotion."GiaTriDonToiThieu")
     OR ((lower(coalesce(promotion."DieuKienApDung",'')) LIKE '%first_order_only%' OR lower(coalesce(promotion."DieuKienApDung",'')) LIKE '%don hang dau tien%' OR lower(coalesce(promotion."DieuKienApDung",'')) LIKE '%đơn hàng đầu tiên%') AND EXISTS(SELECT 1 FROM public."DonHang" WHERE "KhachHangID"=NEW."KhachHangID" AND "DonHangID"<>NEW."DonHangID")) THEN
    IF booking_row."KhuyenMaiDaTru" THEN
      UPDATE public."KhuyenMai"
      SET "SoLuongSuDung" = "SoLuongSuDung" + 1
      WHERE "KhuyenMaiID" = booking_row."KhuyenMaiID"
        AND "SoLuongSuDung" IS NOT NULL;
    END IF;
    UPDATE public."Booking"
    SET "KhuyenMaiID" = NULL,
        "KhuyenMaiDaTru" = false
    WHERE "BookingID" = NEW."BookingID";
    NEW."KhuyenMaiID" := NULL;
    NEW."TienGiamKhuyenMai" := 0;
    NEW."ThanhTien" := greatest(
      NEW."TongTien" + NEW."PhiGiaoHang" - NEW."TienGiamDoDiem",
      0
    );
    RETURN NEW;
  END IF;
  discount_amount := CASE
    WHEN promotion."LoaiKhuyenMai" = 'Phần trăm'
      THEN NEW."TongTien" * promotion."GiaTriGiam" / 100
    ELSE promotion."GiaTriGiam"
  END;
  IF promotion."MucGiamToiDa" IS NOT NULL THEN
    discount_amount := least(discount_amount, promotion."MucGiamToiDa");
  END IF;
  NEW."KhuyenMaiID" := booking_row."KhuyenMaiID";
  NEW."TienGiamKhuyenMai" := greatest(least(discount_amount, NEW."TongTien"), 0);
  NEW."ThanhTien" := greatest(
    NEW."TongTien" + NEW."PhiGiaoHang" - NEW."TienGiamDoDiem" - NEW."TienGiamKhuyenMai",
    0
  );
  RETURN NEW;
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

COMMIT;
