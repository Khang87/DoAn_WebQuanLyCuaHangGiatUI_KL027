<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('garment_id')->nullable()->constrained()->nullOnDelete()->after('service_id');
            $table->decimal('weight', 8, 2)->nullable()->after('quantity');
            // Rename item_name to keep backward compatibility, price stays as historical unit price, subtotal is ThanhTien
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['garment_id']);
            $table->dropColumn(['garment_id', 'weight']);
        });
    }
};