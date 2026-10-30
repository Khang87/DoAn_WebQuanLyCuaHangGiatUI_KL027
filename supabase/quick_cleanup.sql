-- ============================================================================
-- SAFE CLEANUP: Remove Duplicate TaiKhoan Records
-- Run this directly in Supabase Dashboard → SQL Editor
-- 
-- ✅ Fixed: Correct order (delete child tables FIRST)
-- ✅ Fixed: Use CREATE UNIQUE INDEX instead of ALTER TABLE...WHERE
-- ✅ Fixed: Removed duplicate code after COMMIT
-- ============================================================================

BEGIN;

-- =============================================
-- STEP 0: Verify Foreign Keys
-- Shows all tables referencing taikhoanid
-- =============================================
SELECT 
    tc.table_name as referencing_table,
    kcu.column_name as foreign_key_column,
    ccu.table_name as referenced_table,
    ccu.column_name as referenced_column,
    rc.delete_rule as on_delete_action
FROM information_schema.table_constraints AS tc 
JOIN information_schema.key_column_usage AS kcu
  ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage AS ccu
  ON ccu.constraint_name = tc.constraint_name
JOIN information_schema.referential_constraints AS rc
  ON tc.constraint_name = rc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND ccu.table_name = 'taikhoan'
  AND ccu.column_name = 'taikhoanid'
ORDER BY tc.table_name;

-- =============================================
-- STEP 1: Show duplicate emails and count dependencies
-- =============================================
DO $$
DECLARE
    dup_info RECORD;
    v_dep_count INTEGER;
BEGIN
    RAISE NOTICE '═══════════════════════════════════════════════════';
    RAISE NOTICE 'DUPLICATE ACCOUNT ANALYSIS:';
    RAISE NOTICE '═══════════════════════════════════════════════════';
    
    FOR dup_info IN 
        SELECT 
            LOWER(email) as email,
            COUNT(*) as account_count,
            ARRAY_AGG(taikhoanid ORDER BY taikhoanid DESC) as taikhoan_ids,
            MAX(taikhoanid) as keep_id,
            MIN(taikhoanid) as min_id
        FROM taikhoan
        WHERE email IS NOT NULL AND email != ''
        GROUP BY LOWER(email)
        HAVING COUNT(*) > 1
        ORDER BY LOWER(email)
    LOOP
        -- Count dependencies for each duplicate group
        SELECT COUNT(*) INTO v_dep_count
        FROM taikhoan_vaitro WHERE taikhoanid = dup_info.min_id;
        
        RAISE NOTICE '';
        RAISE NOTICE 'Email: %', dup_info.email;
        RAISE NOTICE '  Total accounts: %', dup_info.account_count;
        RAISE NOTICE '  IDs (sorted): %', dup_info.taikhoan_ids;
        RAISE NOTICE '  → KEEP ID: %', dup_info.keep_id;
        RAISE NOTICE '  → DELETE ID: %', dup_info.min_id;
        RAISE NOTICE '  Dependencies to remove (% records):', v_dep_count;
    END LOOP;
    
    RAISE NOTICE '═══════════════════════════════════════════════════';
END $$;

-- =============================================
-- STEP 2: Identify duplicates using ROW_NUMBER()
-- This creates a clean list of IDs to delete
-- =============================================
DROP TEMPORARY TABLE IF EXISTS temp_dups_to_delete;
CREATE TEMPORARY TABLE temp_dups_to_delete AS
SELECT 
    taikhoanid,
    email,
    ROW_NUMBER() OVER (PARTITION BY LOWER(email) ORDER BY taikhoanid DESC) as rn
FROM taikhoan
WHERE email IS NOT NULL AND email != '';

-- Preview what will be deleted
SELECT * FROM temp_dups_to_delete WHERE rn > 1 ORDER BY LOWER(email);

-- =============================================
-- STEP 3: DELETE DEPENDENCIES (Child Tables First!)
-- IMPORTANT: Order matters due to foreign keys!
-- =============================================

-- 3A. Delete from taikhoan_vaitro (role assignments)
DELETE FROM taikhoan_vaitro
WHERE taikhoanid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);
RAISE NOTICE '✓ Deleted role assignments from duplicate accounts';

-- 3B. Delete from thongbao (notifications sent TO user)
DELETE FROM thongbao
WHERE taikhoanid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);
RAISE NOTICE '✓ Deleted notifications from duplicate accounts';

-- 3C. Delete from tinnhan (messages SENT BY user)
DELETE FROM tinnhan
WHERE nguoiguiid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);
RAISE NOTICE '✓ Deleted messages sent by duplicate accounts';

-- 3D. Update tinnhanang to set null where user was recipient
UPDATE thongbao
SET tinhanid = NULL
WHERE tinhanid IN (
    SELECT DISTINCT tinhanid 
    FROM tinnhan 
    WHERE nguoinhanid IN (
        SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
    )
);
RAISE NOTICE '✓ Cleared message references for duplicate account recipients';

-- 3E. Delete from push_device_tokens
DELETE FROM push_device_tokens
WHERE taikhoanid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);
RAISE NOTICE '✓ Deleted device tokens from duplicate accounts';

-- 3F. Delete from nhatkyhe_thong (system logs)
DELETE FROM nhatkyhe_thong
WHERE taikhoanid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);
RAISE NOTICE '✓ Deleted system logs from duplicate accounts';

-- 3G. Delete from lichsu_thaydoi_hoadon (invoice history)
DELETE FROM lichsu_thaydoi_hoadon
WHERE taikhoanid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);
RAISE NOTICE '✓ Deleted invoice history from duplicate accounts';

-- =============================================
-- STEP 4: Delete the duplicate accounts NOW
-- =============================================
DELETE FROM taikhoan
WHERE taikhoanid IN (
    SELECT taikhoanid FROM temp_dups_to_delete WHERE rn > 1
);

-- =============================================
-- STEP 5: Create Partial Unique Indexes (NOT ALTER TABLE)
-- PostgreSQL uses CREATE UNIQUE INDEX with WHERE clause
-- =============================================

-- 5A. Create unique index for email (only non-null, non-empty emails)
CREATE UNIQUE INDEX IF NOT EXISTS taikhoan_email_unique_index
ON "public"."taikhoan" (LOWER(email))
WHERE email IS NOT NULL AND email != '';

-- 5B. Create unique index for userauthid (only non-null Google IDs)
CREATE UNIQUE INDEX IF NOT EXISTS taikhoan_userauthid_unique_index
ON "public"."taikhoan" ("userauthid")
WHERE userauthid IS NOT NULL;

RAISE NOTICE '✓ Created unique indexes to prevent future duplicates';

-- =============================================
-- STEP 6: Verify cleanup success
-- =============================================
DO $$
DECLARE
    remaining_dups INTEGER;
    deleted_count INTEGER;
    total_accounts INTEGER;
BEGIN
    -- Check if any duplicates remain
    SELECT COUNT(*) INTO remaining_dups
    FROM (
        SELECT LOWER(email) as email, COUNT(*) as cnt
        FROM taikhoan
        WHERE email IS NOT NULL AND email != ''
        GROUP BY LOWER(email)
        HAVING COUNT(*) > 1
    ) dups;
    
    -- Get current total
    SELECT COUNT(*) INTO total_accounts FROM taikhoan;
    
    IF remaining_dups = 0 THEN
        RAISE NOTICE '';
        RAISE NOTICE '═══════════════════════════════════════════════════';
        RAISE NOTICE '✅ SUCCESS! All duplicates removed.';
        RAISE NOTICE 'Total accounts remaining: %', total_accounts;
        RAISE NOTICE 'Unique indexes created successfully.';
        RAISE NOTICE '═══════════════════════════════════════════════════';
    ELSE
        RAISE WARNING '';
        RAISE WARNING '═══════════════════════════════════════════════════';
        RAISE WARNING '⚠️ ERROR: Still found % duplicate accounts!', remaining_dups;
        RAISE WARNING 'Transaction will ROLLBACK automatically.';
        RAISE WARNING '═══════════════════════════════════════════════════';
    END IF;
END $$;

COMMIT;

-- Clean up temporary table
DROP TEMPORARY TABLE IF EXISTS temp_dups_to_delete;

-- Final verification query (should return empty result)
SELECT 
    LOWER(email) as duplicate_email,
    COUNT(*) as count,
    ARRAY_AGG(taikhoanid ORDER BY taikhoanid) as kept_ids
FROM taikhoan
WHERE email IS NOT NULL AND email != ''
GROUP BY LOWER(email)
HAVING COUNT(*) > 1
ORDER BY duplicate_email;

-- If you see results above, something went wrong!
SELECT CASE 
    WHEN COUNT(*) = 0 
    THEN '✅ CLEANUP COMPLETE - No duplicates remain!' 
    ELSE '❌ ERROR - Duplicates still exist!' 
END as status
FROM (
    SELECT LOWER(email), COUNT(*) as cnt
    FROM taikhoan
    WHERE email IS NOT NULL AND email != ''
    GROUP BY LOWER(email)
    HAVING COUNT(*) > 1
) dups;
