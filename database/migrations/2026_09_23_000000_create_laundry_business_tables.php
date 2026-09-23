<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->string('type')->default('Mới');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('unit')->default('kg');
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('weight_kg')->nullable();
            $table->string('quantity_items')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pricings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('kg');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method')->default('pickup');
            $table->text('address')->nullable();
            $table->date('pickup_date')->nullable();
            $table->time('pickup_time')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('method')->default('cash');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('discount');
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('pricings');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('services');
        Schema::dropIfExists('customers');
    }
};
