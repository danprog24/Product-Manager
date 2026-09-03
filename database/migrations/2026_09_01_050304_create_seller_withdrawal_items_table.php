<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_withdrawal_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_withdrawal_id')
                ->constrained('seller_withdrawals')
                ->cascadeOnDelete();

            $table->foreignId('seller_earning_id')
                ->constrained('seller_earnings')
                ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);

            $table->timestamps();

            $table->unique([
                'seller_withdrawal_id',
                'seller_earning_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_withdrawal_items');
    }
};