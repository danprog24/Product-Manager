<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE seller_earnings
            DROP CONSTRAINT seller_earnings_status_check
        ");

        DB::statement("
            ALTER TABLE seller_earnings
            ADD CONSTRAINT seller_earnings_status_check
            CHECK (
                status IN (
                    'pending',
                    'available',
                    'reserved',
                    'paid',
                    'cancelled'
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE seller_earnings
            DROP CONSTRAINT seller_earnings_status_check
        ");

        DB::statement("
            ALTER TABLE seller_earnings
            ADD CONSTRAINT seller_earnings_status_check
            CHECK (
                status IN (
                    'pending',
                    'available',
                    'paid',
                    'cancelled'
                )
            )
        ");
    }
};