-- ============================================================================
-- Simple version: Quick duplicate removal for TaiKhoan table
-- Use this if you want a simpler approach
-- ============================================================================

-- Tìm các email bị trùng
SELECT 
    LOWER(email) as email,
    COUNT(*) as count,
    ARRAY_AGG(taikhoanid ORDER BY taikhoanid) as ids
FROM taikhoan
WHERE email IS NOT NULL AND email != ''
GROUP BY LOWER(email)
HAVING COUNT(*) > 1
ORDER BY COUNT(*) DESC;

-- Xóa duplicates - GIỮ LẠI ID cao nhất
WITH dups AS (
    SELECT MIN(taikhoanid) as keep_id
    FROM taikhoan
    WHERE email IS NOT NULL AND email != ''
    GROUP BY LOWER(email)
    HAVING COUNT(*) > 1
),
to_delete AS (
    SELECT t.taikhoanid
    FROM taikhoan t
    INNER JOIN dups ON LOWER(t.email) = (
        SELECT LOWER(email) FROM taikhoan WHERE taikhoanid = dups.keep_id
    )
    WHERE t.taikhoanid != dups.keep_id
)
DELETE FROM taikhoan
WHERE taikhoanid IN (SELECT taikhoanid FROM to_delete);

-- Verify results
SELECT 
    LOWER(email) as email,
    COUNT(*) as remaining_count,
    ARRAY_AGG(taikhoanid ORDER BY taikhoanid) as kept_ids
FROM taikhoan
WHERE email IS NOT NULL AND email != ''
GROUP BY LOWER(email)
HAVING COUNT(*) > 1;
