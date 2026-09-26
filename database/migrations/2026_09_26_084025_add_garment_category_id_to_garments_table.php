<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->foreignId('garment_category_id')
                ->nullable()
                ->after('name')
                ->constrained('garment_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->dropForeign(['garment_category_id']);
            $table->dropColumn('garment_category_id');
        });
    }
};