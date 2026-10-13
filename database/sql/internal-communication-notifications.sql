-- Apply after the existing schema. Preserve the current function grants.
-- All active internal roles receive booking and customer-message notifications.

CREATE OR REPLACE FUNCTION public.notify_customer_of_store_message()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE recipient_customer_id integer; sender_is_staff boolean;
BEGIN
  SELECT a."KhachHangID" INTO recipient_customer_id FROM public."TaiKhoan" AS a
  WHERE a."TaiKhoanID" = NEW."NguoiNhanID" AND a."TrangThai" = 'Hoạt động';
  SELECT EXISTS (
    SELECT 1 FROM public."TaiKhoan" AS sender
    WHERE sender."TaiKhoanID" = NEW."NguoiGuiID"
      AND sender."TrangThai" = 'Hoạt động'
      AND EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS internal_link
      JOIN public."VaiTro" AS internal_role ON internal_role."VaiTroID" = internal_link."VaiTroID"
      WHERE internal_link."TaiKhoanID" = sender."TaiKhoanID"
        AND internal_role."TrangThai" = 'Hoạt động'
    )
    AND NOT EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS customer_link
      JOIN public."VaiTro" AS customer_role ON customer_role."VaiTroID" = customer_link."VaiTroID"
      WHERE customer_link."TaiKhoanID" = sender."TaiKhoanID"
        AND customer_role."TrangThai" = 'Hoạt động'
        AND lower(btrim(customer_role."TenVaiTro")) IN ('khách hàng', 'khach hang', 'khachhang', 'khach-hang', 'customer', 'client')
    )
  ) INTO sender_is_staff;
  IF recipient_customer_id IS NOT NULL THEN
    INSERT INTO public."ThongBao" (
      "TaiKhoanID", "DonHangID", "TinNhanID", "LoaiThongBao", "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc"
    )
    SELECT customer_account."TaiKhoanID", NEW."DonHangID", NEW."TinNhanID", 'new_message',
      CASE WHEN sender_is_staff THEN 'Tin nhắn mới từ cửa hàng' ELSE 'Tin nhắn mới' END,
      'Bạn có tin nhắn mới.', timezone('utc', now())::timestamp, false
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
          AND existing."TinNhanID" = NEW."TinNhanID" AND existing."LoaiThongBao" = 'new_message'
      );
  ELSE
    IF EXISTS (
      SELECT 1 FROM public."TaiKhoan" AS recipient
      WHERE recipient."TaiKhoanID" = NEW."NguoiNhanID"
        AND recipient."TrangThai" = 'Hoạt động'
        AND EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS internal_link
      JOIN public."VaiTro" AS internal_role ON internal_role."VaiTroID" = internal_link."VaiTroID"
      WHERE internal_link."TaiKhoanID" = recipient."TaiKhoanID"
        AND internal_role."TrangThai" = 'Hoạt động'
    )
    AND NOT EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS customer_link
      JOIN public."VaiTro" AS customer_role ON customer_role."VaiTroID" = customer_link."VaiTroID"
      WHERE customer_link."TaiKhoanID" = recipient."TaiKhoanID"
        AND customer_role."TrangThai" = 'Hoạt động'
        AND lower(btrim(customer_role."TenVaiTro")) IN ('khách hàng', 'khach hang', 'khachhang', 'khach-hang', 'customer', 'client')
    )
    ) AND EXISTS (
      SELECT 1 FROM public."TaiKhoan" AS sender
      WHERE sender."TaiKhoanID" = NEW."NguoiGuiID"
        AND sender."KhachHangID" IS NOT NULL
        AND sender."NhanVienID" IS NULL
    ) THEN
      INSERT INTO public."ThongBao" (
        "TaiKhoanID", "DonHangID", "TinNhanID", "LoaiThongBao",
        "TieuDe", "NoiDung", "ThoiGianGui", "DaDoc"
      )
      SELECT staff."TaiKhoanID", NEW."DonHangID", NEW."TinNhanID",
        'new_message', 'Tin nhắn mới từ khách hàng', 'Bạn có tin nhắn mới.',
        timezone('utc', now())::timestamp, false
      FROM public."TaiKhoan" AS staff
      WHERE staff."TrangThai" = 'Hoạt động'
        AND EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS internal_link
      JOIN public."VaiTro" AS internal_role ON internal_role."VaiTroID" = internal_link."VaiTroID"
      WHERE internal_link."TaiKhoanID" = staff."TaiKhoanID"
        AND internal_role."TrangThai" = 'Hoạt động'
    )
    AND NOT EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS customer_link
      JOIN public."VaiTro" AS customer_role ON customer_role."VaiTroID" = customer_link."VaiTroID"
      WHERE customer_link."TaiKhoanID" = staff."TaiKhoanID"
        AND customer_role."TrangThai" = 'Hoạt động'
        AND lower(btrim(customer_role."TenVaiTro")) IN ('khách hàng', 'khach hang', 'khachhang', 'khach-hang', 'customer', 'client')
    );
    END IF;
  END IF;
  RETURN NEW;
END
$function$;

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
  WHERE EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS internal_link
      JOIN public."VaiTro" AS internal_role ON internal_role."VaiTroID" = internal_link."VaiTroID"
      WHERE internal_link."TaiKhoanID" = account."TaiKhoanID"
        AND internal_role."TrangThai" = 'Hoạt động'
    )
    AND NOT EXISTS (
      SELECT 1 FROM public."TaiKhoan_VaiTro" AS customer_link
      JOIN public."VaiTro" AS customer_role ON customer_role."VaiTroID" = customer_link."VaiTroID"
      WHERE customer_link."TaiKhoanID" = account."TaiKhoanID"
        AND customer_role."TrangThai" = 'Hoạt động'
        AND lower(btrim(customer_role."TenVaiTro")) IN ('khách hàng', 'khach hang', 'khachhang', 'khach-hang', 'customer', 'client')
    )
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
$function$;

-- Booking inserts invoke this through a postgres-owned SECURITY DEFINER trigger.
-- Client RPC access could otherwise forge notifications with arbitrary text.
REVOKE EXECUTE ON FUNCTION public.notify_staff_new_booking(bigint, text, text) FROM authenticated;
