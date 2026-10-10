-- Additive migration. No TinNhan RLS/grants or mobile RPC changes.
CREATE SCHEMA IF NOT EXISTS private;
CREATE TABLE IF NOT EXISTS private.web_message_channels (
    scope text NOT NULL CHECK (scope ~ '^(order|support):[1-9][0-9]*$'),
    slot bigint NOT NULL,
    topic text NOT NULL UNIQUE,
    expires_at timestamptz NOT NULL,
    PRIMARY KEY (scope, slot)
);
CREATE INDEX IF NOT EXISTS web_message_channels_scope_expiry ON private.web_message_channels (scope, expires_at);
CREATE INDEX IF NOT EXISTS web_message_channels_expiry ON private.web_message_channels (expires_at);
ALTER TABLE private.web_message_channels ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON private.web_message_channels FROM PUBLIC;
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
        REVOKE ALL ON private.web_message_channels FROM anon;
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        REVOKE ALL ON private.web_message_channels FROM authenticated;
    END IF;
END $$;

CREATE OR REPLACE FUNCTION private.broadcast_web_message_change()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path = '' AS $$
DECLARE
    item jsonb;
    scopes text[] := ARRAY[]::text[];
    channel record;
BEGIN
    -- Include both sides of UPDATE/move and DELETE; never disclose row data.
    FOREACH item IN ARRAY ARRAY[CASE WHEN TG_OP <> 'INSERT' THEN to_jsonb(OLD) END,
                                CASE WHEN TG_OP <> 'DELETE' THEN to_jsonb(NEW) END] LOOP
        IF item IS NULL THEN CONTINUE; END IF;
        IF item->>'DonHangID' IS NOT NULL THEN
            scopes := array_append(scopes, 'order:' || (item->>'DonHangID'));
        ELSE
            scopes := scopes || ARRAY(
                SELECT 'support:' || customer."TaiKhoanID"::text
                FROM public."TaiKhoan" customer
                JOIN public."TaiKhoan" staff ON staff."TaiKhoanID" = CASE
                    WHEN customer."TaiKhoanID" = (item->>'NguoiGuiID')::bigint
                    THEN (item->>'NguoiNhanID')::bigint ELSE (item->>'NguoiGuiID')::bigint END
                WHERE customer."TaiKhoanID" IN ((item->>'NguoiGuiID')::bigint, (item->>'NguoiNhanID')::bigint)
                  AND customer."KhachHangID" IS NOT NULL AND customer."NhanVienID" IS NULL
                  AND staff."NhanVienID" IS NOT NULL
            );
        END IF;
    END LOOP;
    FOR channel IN SELECT topic FROM private.web_message_channels
        WHERE scope = ANY(scopes) AND expires_at > clock_timestamp()
        ORDER BY expires_at DESC LIMIT 8 LOOP
        BEGIN
            PERFORM realtime.send('{}'::jsonb, 'changed', channel.topic, false);
        EXCEPTION WHEN query_canceled OR OTHERS THEN
            -- Delivery is best effort: a Realtime outage must not break message writes.
            RAISE WARNING 'Web message signal delivery failed';
        END;
    END LOOP;
    RETURN NULL;
EXCEPTION WHEN query_canceled OR OTHERS THEN
    RAISE WARNING 'Web message signal unavailable';
    RETURN NULL;
END;
$$;
REVOKE ALL ON FUNCTION private.broadcast_web_message_change() FROM PUBLIC;
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'public."TinNhan"'::regclass
                   AND tgname = 'web_message_changed' AND NOT tgisinternal) THEN
        CREATE TRIGGER web_message_changed AFTER INSERT OR UPDATE OR DELETE ON public."TinNhan"
        FOR EACH ROW EXECUTE FUNCTION private.broadcast_web_message_change();
    END IF;
END $$;
