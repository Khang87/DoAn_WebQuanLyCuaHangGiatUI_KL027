<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () {
    /**
     * Xóa dữ liệu tài khoản trùng lặp dựa trên Email.
     * Giữ lại bản ghi có TaiKhoanID cao nhất trong mỗi nhóm trùng.
     * 
     * Quy trình:
     * 1. Tìm các email bị trùng (count > 1)
     * 2. Với mỗi email, xác định các TaiKhoanID cần xóa (trừ cái cao nhất)
     * 3. Xóa quan hệ vai trò và thông báo trước khi xóa tài khoản
     * 4. Xóa bản ghi tài khoản trùng
     * 
     * Lưu ý: Không xóa nhân viên hoặc khách hàng - chỉ xóa tài khoản đăng nhập
     */
    public function up(): void
    {
        DB::statement('BEGIN;');

        try {
            // Bước 1: Tạo temporary table chứa thông tin duplicates
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_duplicate_emails;');
            DB::statement('
                CREATE TEMPORARY TABLE temp_duplicate_emails AS
                SELECT 
                    LOWER(email) as email_lower,
                    ARRAY_AGG(taikhoanid ORDER BY taikhoanid) as all_ids,
                    MAX(taikhoanid) as keep_id
                FROM taikhoan
                WHERE email IS NOT NULL 
                    AND email != \'\'' 
                GROUP BY LOWER(email)
                HAVING COUNT(*) > 1;
            ');

            // Bước 2: Lấy danh sách tất cả TaiKhoanID cần xóa
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_ids_to_delete;');
            DB::statement('
                CREATE TEMPORARY TABLE temp_ids_to_delete AS
                SELECT 
                    unnest(all_ids) as taikhoanid_to_delete
                FROM temp_duplicate_emails
                WHERE keep_id != unnest(all_ids);
            ');

            // Bước 3: In thông tin để logging
            $duplicateCount = DB::table('temp_ids_to_delete')->count();
            if ($duplicateCount > 0) {
                echo "Found {$duplicateCount} duplicate TaiKhoan records to remove.\n";
                
                // Hiển thị danh sách emails trùng
                $duplicates = DB::select('
                    SELECT DISTINCT LOWER(t.email) as email, 
                           ARRAY_AGG(t.taikhoanid ORDER BY t.taikhoanid) as ids,
                           MAX(t.taikhoanid) as keep_id
                    FROM taikhoan t
                    INNER JOIN temp_ids_to_delete d ON t.taikhoanid = d.taikhoanid_to_delete
                    GROUP BY LOWER(t.email)
                    ORDER BY LOWER(t.email);
                ');
                
                foreach ($duplicates as $dup) {
                    echo "Email: {$dup->email}\n";
                    echo "  - Keep ID: {$dup->keep_id}\n";
                    echo "  - Delete IDs: " . implode(', ', array_diff($dup->ids, [(int)$dup->keep_id])) . "\n";
                }
            } else {
                echo "No duplicate TaiKhoan records found.\n";
            }

            // Bước 4: Xóa các liên kết phụ thuộc TRƯỚC khi xóa tài khoản
            // Xóa role assignments
            DB::table('taikhoan_vaitro')
                ->whereIn('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Xóa notifications (thông báo)
            DB::table('thongbao')
                ->whereIn('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Xóa messages (người gửi)
            DB::table('tinnhan')
                ->where('nguoiguiid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Xóa messages (người nhận) - cập nhật null thay vì xóa
            DB::table('thongbao')
                ->where('tinhanid', DB::select('
                    SELECT tinhanid FROM tinnhan WHERE nguoinhanid IN (
                        SELECT taikhoanid_to_delete FROM temp_ids_to_delete
                    )
                '))
                ->update(['tinhanid' => null]);

            // Xóa tin nhắn đã đọc
            DB::table('tinnhanan')
                ->where('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Xóa device tokens
            DB::table('push_device_tokens')
                ->whereIn('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Xóa system logs
            DB::table('nhatkyhe_thong')
                ->where('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Xóa lịch sử hóa đơn
            DB::table('lichsuthaydoihoa_don')
                ->where('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            // Bước 5: Xóa tài khoản trùng
            $deletedCount = DB::table('taikhoan')
                ->whereIn('taikhoanid', DB::table('temp_ids_to_delete')->pluck('taikhoanid_to_delete'))
                ->delete();

            echo "Deleted {$deletedCount} duplicate TaiKhoan records.\n";

            DB::statement('COMMIT;');
        } catch (\Exception $e) {
            DB::statement('ROLLBACK;');
            throw $e;
        } finally {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_duplicate_emails;');
            DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_ids_to_delete;');
        }
    }

    public function down(): void
    {
        // Không thể restore data đã xóa - chỉ log cảnh báo
        echo "Warning: This migration cannot be rolled back. Duplicate data will not be restored.\n";
    }
};
