<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');
        });

        // Gán mã tham chiếu cho các lịch hẹn đã tồn tại trước khi có cột code.
        DB::table('bookings')->orderBy('id')->each(function ($booking) {
            DB::table('bookings')
                ->where('id', $booking->id)
                ->update(['code' => 'DL' . str_pad((string) $booking->id, 4, '0', STR_PAD_LEFT)]);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
