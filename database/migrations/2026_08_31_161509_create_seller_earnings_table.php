<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_earnings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete();

            $table->decimal('gross_amount', 10, 2);

            $table->decimal('commission', 10, 2)
                ->default(0);

            $table->decimal('net_amount', 10, 2);

            $table->enum('status', [
                'pending',
                'available',
                'paid',
                'cancelled',
            ])->default('pending');

            $table->timestamps();

            $table->unique('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_earnings');
    }
};