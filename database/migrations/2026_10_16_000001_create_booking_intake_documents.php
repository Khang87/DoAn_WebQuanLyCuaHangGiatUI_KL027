<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('BookingGiaoNhan', function (Blueprint $table): void {
            $table->increments('BookingGiaoNhanID');
            $table->unsignedInteger('BookingID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->unsignedInteger('BookingKiemKeID')->nullable();
            $table->string('LoaiGiaoNhan', 30);
            $table->string('HinhThuc', 30);
            $table->string('DiaChi', 255)->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 12, 2)->default(0);
            $table->string('TrangThai', 30)->default('Chờ thực hiện');
            $table->dateTime('NgayTao');
            $table->unique(['BookingID', 'LoaiGiaoNhan']);
            $table->index('DonHangID');
            $table->index('BookingKiemKeID');
            $table->foreign('BookingID')->references('BookingID')->on('Booking')->cascadeOnDelete();
            $table->foreign('DonHangID')->references('DonHangID')->on('DonHang')->nullOnDelete();
            $table->foreign('NhanVienID')->references('NhanVienID')->on('NhanVien')->nullOnDelete();
        });

        Schema::create('BookingKiemKe', function (Blueprint $table): void {
            $table->increments('BookingKiemKeID');
            $table->unsignedInteger('BookingID')->unique();
            $table->json('DuLieuThucTe');
            $table->boolean('CoChenhLech');
            $table->unsignedInteger('PhienBan')->default(1);
            $table->dateTime('CapNhatLuc');
            $table->foreign('BookingID')->references('BookingID')->on('Booking')->cascadeOnDelete();
        });
        Schema::table('BookingGiaoNhan', function (Blueprint $table): void {
            $table->foreign('BookingKiemKeID')->references('BookingKiemKeID')->on('BookingKiemKe')->nullOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "BookingGiaoNhan" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "BookingKiemKe" ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('BookingKiemKe');
        Schema::dropIfExists('BookingGiaoNhan');
    }
};
