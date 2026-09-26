<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * orders.booking_id = tham chiếu đặt lịch đã được chuyển thành đơn hàng.
     *
     * Duy nhất (unique) để bảo đảm một đặt lịch chỉ sinh ra một đơn: xác nhận
     * lại cùng một đặt lịch sẽ không tạo đơn trùng. NULL cho các đơn tạo
     * thường — NULL vẫn được phép lặp lại trong chỉ mục unique.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('booking_id')
                ->nullable()
                ->after('promotion_id')
                ->constrained('bookings')
                ->nullOnDelete()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
        });
    }
};
