<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('StaffApiToken', function (Blueprint $table): void {
            $table->bigIncrements('StaffApiTokenID');
            $table->unsignedInteger('TaiKhoanID');
            $table->char('TokenHash', 64)->unique();
            $table->dateTime('NgayHetHan');
            $table->dateTime('NgayTao');
            $table->foreign('TaiKhoanID')->references('TaiKhoanID')->on('TaiKhoan')->cascadeOnDelete();
            $table->index(['TaiKhoanID', 'NgayHetHan']);
        });
        Schema::create('StaffBookingDraft', function (Blueprint $table): void {
            $table->unsignedInteger('BookingID')->primary();
            $table->json('Payload');
            $table->boolean('CoChenhLech')->default(false);
            $table->dateTime('CapNhatLuc');
            $table->foreign('BookingID')->references('BookingID')->on('Booking')->cascadeOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "StaffApiToken" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "StaffBookingDraft" ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL ON "StaffApiToken", "StaffBookingDraft" FROM PUBLIC, anon, authenticated');
            DB::statement('GRANT ALL ON "StaffApiToken", "StaffBookingDraft" TO service_role');
            DB::statement('GRANT USAGE, SELECT ON SEQUENCE "StaffApiToken_StaffApiTokenID_seq" TO service_role');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('StaffBookingDraft');
        Schema::dropIfExists('StaffApiToken');
    }
};
