-- Test-only transport boundary: record sends without external network calls.
CREATE SCHEMA realtime;
CREATE TABLE realtime.test_signals (payload jsonb, event text, topic text, private boolean);
CREATE FUNCTION realtime.send(payload jsonb, event text, topic text, private boolean DEFAULT true)
RETURNS void LANGUAGE plpgsql AS $$ BEGIN
    IF current_setting('test.broadcast_cancel', true) = 'true' THEN RAISE EXCEPTION USING ERRCODE = '57014', MESSAGE = 'Test transport timeout'; END IF;
    IF current_setting('test.broadcast_failure', true) = 'true' THEN RAISE EXCEPTION 'Test transport unavailable'; END IF;
    INSERT INTO realtime.test_signals VALUES (payload, event, topic, private);
END $$;
\ir ../../database/sql/web-message-realtime.sql
