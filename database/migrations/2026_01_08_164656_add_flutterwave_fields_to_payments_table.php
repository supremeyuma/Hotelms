<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('NGN')->after('amount');
            $table->string('status')->default('pending')->after('currency');
            $table->string('flutterwave_tx_id')->nullable()->after('reference');
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'status',
                'flutterwave_tx_id',
                'raw_response',
                'paid_at',
            ]);
        });
    }
};
