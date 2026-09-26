<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Model Pricing dùng SoftDeletes nhưng bảng pricings thiếu cột deleted_at,
        // khiến mọi truy vấn Pricing ném lỗi "no such column: pricings.deleted_at".
        if (! Schema::hasColumn('pricings', 'deleted_at')) {
            Schema::table('pricings', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pricings', 'deleted_at')) {
            Schema::table('pricings', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
