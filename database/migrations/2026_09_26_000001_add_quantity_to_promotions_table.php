<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * promotions.quantity = số lượng mã khuyến mãi được phát ra.
     *
     * NULL nghĩa là phát vô hạn. Khi used_count >= quantity thì voucher hết hạn
     * dùng (Promotion::isValid()).
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('used_count');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
