-- Disposable PostgreSQL test prerequisites only, not Supabase platform migrations.
CREATE ROLE anon;
CREATE ROLE authenticated;
CREATE ROLE service_role BYPASSRLS;
CREATE ROLE supabase_admin;
CREATE SCHEMA auth;
CREATE SCHEMA storage;
CREATE SCHEMA realtime;
CREATE SCHEMA extensions;
-- Emulate a new Supabase target whose pre-existing defaults grant client access.
-- The source snapshot must remove these grants (especially on non-RLS sessions).
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON TABLES TO anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON SEQUENCES TO anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public GRANT ALL ON FUNCTIONS TO anon, authenticated;
CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA extensions;
CREATE FUNCTION auth.uid() RETURNS uuid LANGUAGE sql STABLE AS
$$ SELECT nullif(current_setting('test.user_id', true), '')::uuid $$;
CREATE FUNCTION auth.role() RETURNS text LANGUAGE sql STABLE AS
$$ SELECT nullif(current_setting('request.jwt.claim.role', true), '') $$;
CREATE FUNCTION auth.jwt() RETURNS jsonb LANGUAGE sql STABLE AS
$$ SELECT coalesce(nullif(current_setting('request.jwt.claims', true), '')::jsonb, '{}'::jsonb) $$;
CREATE TABLE storage.objects (bucket_id text, owner_id text, name text);
ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;
CREATE FUNCTION storage.foldername(text) RETURNS text[] LANGUAGE sql IMMUTABLE AS
$$ SELECT (string_to_array($1, '/'))[1:array_length(string_to_array($1, '/'), 1)-1] $$;
CREATE FUNCTION realtime.send(jsonb, text, text, boolean) RETURNS void LANGUAGE sql AS $$ SELECT $$;
CREATE PUBLICATION supabase_realtime;
