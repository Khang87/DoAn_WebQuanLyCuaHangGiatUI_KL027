<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricings', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->foreignId('garment_id')->nullable()->constrained()->nullOnDelete()->after('service_id');
            $table->date('effective_date')->nullable()->after('garment_id');
            $table->string('status')->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pricings', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropForeign(['garment_id']);
            $table->dropColumn(['service_id', 'garment_id', 'effective_date']);
            $table->string('status')->default('active')->change();
        });
    }
};