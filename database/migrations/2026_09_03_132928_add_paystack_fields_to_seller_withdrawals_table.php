<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_withdrawals', function (Blueprint $table) {
            $table->string('bank_code', 20)
                ->nullable()
                ->after('bank_name');

            $table->string('paystack_recipient_code')
                ->nullable()
                ->after('bank_code');

            $table->string('paystack_transfer_reference', 50)
                ->nullable()
                ->unique()
                ->after('paystack_recipient_code');

            $table->string('paystack_transfer_code')
                ->nullable()
                ->after('paystack_transfer_reference');

            $table->unsignedBigInteger('paystack_transfer_id')
                ->nullable()
                ->after('paystack_transfer_code');
        });
    }

    public function down(): void
    {
        Schema::table('seller_withdrawals', function (Blueprint $table) {
            $table->dropUnique([
                'paystack_transfer_reference'
            ]);

            $table->dropColumn([
                'bank_code',
                'paystack_recipient_code',
                'paystack_transfer_reference',
                'paystack_transfer_code',
                'paystack_transfer_id',
            ]);
        });
    }
};