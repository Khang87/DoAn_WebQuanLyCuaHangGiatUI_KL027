<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('DanhGia', function (Blueprint $table): void {
            $table->text('shop_response')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('DanhGia', function (Blueprint $table): void {
            $table->dropColumn('shop_response');
        });
    }
};
