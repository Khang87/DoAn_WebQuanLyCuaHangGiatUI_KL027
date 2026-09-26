<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('discount_type')->default('fixed')->after('code'); // 'percentage' or 'fixed'
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->decimal('min_order_amount', 12, 2)->default(0)->after('discount_value');
            $table->decimal('max_discount', 12, 2)->nullable()->after('min_order_amount');
            $table->unsignedInteger('usage_limit')->nullable()->after('max_discount');
            $table->json('conditions')->nullable()->after('usage_limit');
            $table->date('starts_at')->nullable()->after('conditions');
            $table->renameColumn('expires_at', 'expires_at'); // keep existing
            $table->string('status')->default('active')->change();
        });
        
        // Remove old discount column
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('discount')->nullable();
            $table->dropColumn(['discount_type', 'discount_value', 'min_order_amount', 'max_discount', 'usage_limit', 'conditions', 'starts_at']);
            $table->string('status')->default('active')->change();
        });
    }
};