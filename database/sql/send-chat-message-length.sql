-- Targeted non-destructive fix: match TinNhan.NoiDung varchar(1000).
-- Preserve the current signature, authorization and return contract.
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
