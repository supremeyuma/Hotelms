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
        Schema::table('charges', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid')->after('amount');
            $table->enum('payment_mode', ['prepaid', 'pay_on_delivery', 'postpaid'])
                ->default('postpaid')
                ->after('status');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            //
        });
    }
};
