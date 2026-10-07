-- Definitions captured from QLGiatUi before this change; restore only as a complete rollback.
BEGIN;
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
$function$;

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
$function$;

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
        points_to_use := LEAST(
          available_points::numeric,
          FLOOR(total_amount)
        )::integer;
        points_discount := points_to_use;

        IF points_to_use > 0 THEN
          UPDATE public."DiemTichLuy"
          SET
            "DiemHienTai" = "DiemHienTai" - points_to_use,
            "NgayCapNhat" = now()
          WHERE "KhachHangID" = customer_id;
        END IF;
      END IF;
    END IF;

    UPDATE public."Booking"
    SET
      "DiemSuDung" = points_to_use,
      "TienGiamDoDiem" = points_discount,
      "DiemDaTru" = true
    WHERE "BookingID" = booking_id;
  END IF;

  SELECT COALESCE("DiemHienTai", 0)
  INTO remaining_points
  FROM public."DiemTichLuy"
  WHERE "KhachHangID" = customer_id;
  remaining_points := COALESCE(remaining_points, 0);

  SELECT *
  INTO booking_row
  FROM public."Booking"
  WHERE "BookingID" = booking_id;

  RETURN booking_result || jsonb_build_object(
    'thanhtien', total_amount,
    'diemsudung', booking_row."DiemSuDung",
    'tiengiamdodiem', booking_row."TienGiamDoDiem",
    'thanhtoan', GREATEST(
      total_amount - booking_row."TienGiamDoDiem",
      0
    ),
    'diemconlai', remaining_points
  );
END;
$function$;

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
$function$;

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

  IF booking_row."TrangThai" <> 'ChoTiepNhan'
     OR EXISTS (SELECT 1 FROM public."DonHang" WHERE "BookingID" = p_bookingid) THEN
    RAISE EXCEPTION 'Only pending bookings can be canceled';
  END IF;

  UPDATE public."Booking"
  SET "TrangThai" = 'DaHuy',
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
$function$;

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
$function$;

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

  IF promotion."GiaTriDonToiThieu" IS NOT NULL
     AND NEW."TongTien" < promotion."GiaTriDonToiThieu" THEN
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
$function$;

COMMIT;
