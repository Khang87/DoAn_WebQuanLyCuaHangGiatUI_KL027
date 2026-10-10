-- Run only after the disposable snapshot restore, including permissive defaults.
DO $$
BEGIN
    IF has_table_privilege('anon', 'public.sessions', 'SELECT')
       OR has_table_privilege('authenticated', 'public.sessions', 'SELECT') THEN
        RAISE EXCEPTION 'Client roles inherited access to Laravel sessions';
    END IF;
    IF NOT has_column_privilege('authenticated', 'public."KhachHang"', 'HoTen', 'UPDATE')
       OR has_column_privilege('authenticated', 'public."KhachHang"', 'TrangThai', 'UPDATE') THEN
        RAISE EXCEPTION 'Customer profile column-level UPDATE contract changed';
    END IF;
    IF NOT has_column_privilege('authenticated', 'public."ThongBao"', 'DaDoc', 'UPDATE')
       OR has_column_privilege('authenticated', 'public."ThongBao"', 'TaiKhoanID', 'UPDATE') THEN
        RAISE EXCEPTION 'Notification read-state/ownership update boundary changed';
    END IF;
    IF has_table_privilege('anon', 'private.web_message_channels', 'SELECT')
       OR has_table_privilege('authenticated', 'private.web_message_channels', 'SELECT') THEN
        RAISE EXCEPTION 'Client can read private message channel registry';
    END IF;
END;
$$;
