<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chuẩn hoá tên danh mục dịch vụ về kiểu câu (chỉ chữ cái đầu được viết hoá,
 * sau `&` và `/` vẫn viết thường) kèm sửa lỗi chính tả "hàng ngày" -> "hằng ngày".
 *
 * Đây là thay đổi THUẦN HIỂN THỊ: cột `slug` cố tình KHÔNG bị đụng tới, vì
 * ServiceSeeder và các truy vấn khác tra cứu danh mục theo slug — đổi slug sẽ
 * làm hỏng các liên kết dữ liệu đang tồn tại.
 *
 * Dùng DB::table() nên cả các bản ghi đã soft-delete cũng được cập nhật. Mỗi
 * dòng là một lệnh update độc lập, nên nếu thiếu dòng tương ứng thì chỉ là
 * no-op. Khoá so khớp là `slug` (4 dòng dịch vụ) và `code` (5 dòng
 * CAT_ trong CategorySeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_categories')->where('slug', 'giat-say-quan-ao')->update(['name' => 'Giặt sấy quần áo']);
        DB::table('service_categories')->where('slug', 'giat-hap-giat-kho-cao-cap')->update(['name' => 'Giặt hấp / giặt khô cao cấp']);
        DB::table('service_categories')->where('slug', 'giat-chan-ga-nem-tham')->update(['name' => 'Giặt chăn ga nệm & thảm']);
        DB::table('service_categories')->where('slug', 'cham-soc-giay-phu-kien')->update(['name' => 'Chăm sóc giày & phụ kiện']);
        DB::table('service_categories')->where('code', 'CAT_QUAN_AO')->update(['name' => 'Quần áo thường hằng ngày']);
        DB::table('service_categories')->where('code', 'CAT_CAO_CAP')->update(['name' => 'Đồ cao cấp & tế nhị']);
        DB::table('service_categories')->where('code', 'CAT_CHANNGU')->update(['name' => 'Chăn ga & mền gối']);
        DB::table('service_categories')->where('code', 'CAT_GIAY_TUI')->update(['name' => 'Giày & túi xách / phụ kiện']);
        DB::table('service_categories')->where('code', 'CAT_REM_THAM')->update(['name' => 'Rèm cửa & thảm trải sàn']);
    }

    public function down(): void
    {
        DB::table('service_categories')->where('slug', 'giat-say-quan-ao')->update(['name' => 'Giặt Sấy Quần Áo']);
        DB::table('service_categories')->where('slug', 'giat-hap-giat-kho-cao-cap')->update(['name' => 'Giặt Hấp / Giặt Khô Cao Cấp']);
        DB::table('service_categories')->where('slug', 'giat-chan-ga-nem-tham')->update(['name' => 'Giặt Chăn Ga Nệm & Thảm']);
        DB::table('service_categories')->where('slug', 'cham-soc-giay-phu-kien')->update(['name' => 'Chăm Sóc Giày & Phụ Kiện']);
        DB::table('service_categories')->where('code', 'CAT_QUAN_AO')->update(['name' => 'Quần áo thường hàng ngày']);
        DB::table('service_categories')->where('code', 'CAT_CAO_CAP')->update(['name' => 'Đồ cao cấp & Tế nhị']);
        DB::table('service_categories')->where('code', 'CAT_CHANNGU')->update(['name' => 'Chăn ga & Mền gối']);
        DB::table('service_categories')->where('code', 'CAT_GIAY_TUI')->update(['name' => 'Giày & Túi xách / Phụ kiện']);
        DB::table('service_categories')->where('code', 'CAT_REM_THAM')->update(['name' => 'Rèm cửa & Thảm trải sàn']);
    }
};
