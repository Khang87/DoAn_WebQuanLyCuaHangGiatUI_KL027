<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chuẩn hóa bộ số tiền của đơn hàng:
     *   subtotal              = Tạm tính            (Σ số lượng × đơn giá lịch sử)
     *   discount_by_promotion = Tiền giảm voucher
     *   discount_by_points    = Tiền giảm do điểm
     *   total_amount          = Tổng thanh toán cuối cùng
     *
     * Thay thế hai cột cũ discount_amount / points_discount để tránh
     * tồn tại nhiều nguồn số tiền giảm trên cùng một đơn.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('total_amount');
            $table->decimal('discount_by_promotion', 12, 2)->default(0)->after('subtotal');
            $table->decimal('discount_by_points', 12, 2)->default(0)->after('discount_by_promotion');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'points_discount']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount');
            $table->decimal('points_discount', 12, 2)->default(0)->after('points_used');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'discount_by_promotion', 'discount_by_points']);
        });
    }
};
