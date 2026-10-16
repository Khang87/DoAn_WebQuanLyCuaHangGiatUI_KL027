DO $$
BEGIN
  IF NOT (SELECT relrowsecurity FROM pg_class WHERE oid = 'public."BookingKiemKe"'::regclass)
     OR NOT (SELECT relrowsecurity FROM pg_class WHERE oid = 'public."BookingGiaoNhan"'::regclass) THEN
    RAISE EXCEPTION 'Booking intake tables require RLS';
  END IF;
  IF NOT has_table_privilege('authenticated', 'public."BookingKiemKe"', 'SELECT')
     OR NOT has_column_privilege('authenticated', 'public."BookingKiemKe"', 'XacNhanPhienBan', 'UPDATE')
     OR has_column_privilege('authenticated', 'public."BookingKiemKe"', 'DuLieuThucTe', 'UPDATE')
     OR has_column_privilege('authenticated', 'public."BookingKiemKe"', 'PhienBan', 'UPDATE')
     OR has_table_privilege('anon', 'public."BookingKiemKe"', 'SELECT') THEN
    RAISE EXCEPTION 'Booking inspection grants exceed the customer acknowledgement contract';
  END IF;
  IF (SELECT count(*) FROM pg_policies WHERE schemaname = 'public'
      AND tablename = 'BookingKiemKe' AND policyname IN
      ('booking_inspection_customer_read', 'booking_inspection_customer_ack')) <> 2 THEN
    RAISE EXCEPTION 'Booking inspection ownership policies are missing';
  END IF;
END;
$$;
