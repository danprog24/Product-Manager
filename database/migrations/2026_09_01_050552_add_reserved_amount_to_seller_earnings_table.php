<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_earnings', function (Blueprint $table) {
            $table->decimal('reserved_amount', 10, 2)
                ->default(0)
                ->after('net_amount');
        });
    }

    public function down(): void
    {
        Schema::table('seller_earnings', function (Blueprint $table) {
            $table->dropColumn('reserved_amount');
        });
    }
};