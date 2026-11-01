BEGIN;

ALTER TABLE public."DichVu"
    ADD COLUMN IF NOT EXISTS "Icon" character varying(64);

COMMIT;
