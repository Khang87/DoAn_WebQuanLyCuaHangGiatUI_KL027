<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete()->after('customer_id');
            $table->renameColumn('delivery_method', 'method');
            $table->string('method')->default('nhan_do')->change(); // 'nhan_do' (Nhận đồ) / 'giao_do' (Giao đồ)
            $table->renameColumn('pickup_date', 'scheduled_date');
            $table->renameColumn('pickup_time', 'scheduled_time');
            $table->dropForeign(['service_id']);
            $table->dropColumn(['service_id', 'garment_type', 'quantity']);
        });
        
        // Update existing statuses to new ones
        \DB::statement("UPDATE bookings SET status = CASE 
            WHEN status = 'pending' THEN 'pending'
            WHEN status = 'confirmed' THEN 'confirmed'
            WHEN status = 'cancelled' THEN 'cancelled'
            ELSE 'pending' END");
        \DB::statement("UPDATE bookings SET method = CASE 
            WHEN method IN ('pickup', 'home_pickup') THEN 'nhan_do'
            WHEN method IN ('dropoff', 'delivery', 'store_delivery') THEN 'giao_do'
            ELSE 'nhan_do' END");
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn(['staff_id']);
            $table->renameColumn('method', 'delivery_method');
            $table->string('delivery_method')->default('pickup')->change();
            $table->renameColumn('scheduled_date', 'pickup_date');
            $table->renameColumn('scheduled_time', 'pickup_time');
            $table->foreignId('service_id')->constrained()->cascadeOnDelete()->after('customer_id');
            $table->string('garment_type')->after('service_id');
            $table->integer('quantity')->default(1)->after('garment_type');
        });
    }
};