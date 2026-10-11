-- Keep accepted mobile fees, excluding legs inspected as at-store.
-- Replaces only this existing trigger function; no rows or privileges are changed.
CREATE OR REPLACE FUNCTION private.copy_booking_delivery_fee_to_order()
 RETURNS trigger
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO ''
AS $function$
DECLARE
  pickup_fee numeric(12, 2);
  delivery_fee numeric(12, 2);
BEGIN
  IF NEW."BookingID" IS NULL THEN
    RETURN NEW;
  END IF;

  SELECT
    CASE WHEN "HinhThucNhanDo" = 'Tại nhà' THEN coalesce("PickupDeliveryFee", 0) ELSE 0 END,
    CASE WHEN coalesce("HinhThucTraDo", "HinhThucGiaoDo") = 'Tại nhà' THEN coalesce("DeliveryFee", 0) ELSE 0 END
  INTO pickup_fee, delivery_fee
  FROM public."Booking"
  WHERE "BookingID" = NEW."BookingID";

  IF FOUND THEN
    IF pickup_fee < 0 OR delivery_fee < 0 THEN
      RAISE EXCEPTION 'Booking delivery fees must be nonnegative' USING ERRCODE = '23514';
    END IF;
    NEW."PhiGiaoHang" := pickup_fee + delivery_fee;
    NEW."ThanhTien" := greatest(
      NEW."TongTien" - least(NEW."TongTien", greatest(NEW."TienGiamKhuyenMai", 0))
        - greatest(NEW."TienGiamDoDiem", 0), 0
    ) + NEW."PhiGiaoHang";
  END IF;
  RETURN NEW;
END;
$function$;
