<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('GiaoNhan') || Schema::hasColumn('GiaoNhan', 'NgayTao')) {
            return;
        }

        Schema::table('GiaoNhan', function (Blueprint $table): void {
            $table->dateTime('NgayTao')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('GiaoNhan') || ! Schema::hasColumn('GiaoNhan', 'NgayTao')) {
            return;
        }

        Schema::table('GiaoNhan', function (Blueprint $table): void {
            $table->dropColumn('NgayTao');
        });
    }
};
