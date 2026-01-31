<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns only if they don't already exist (avoid duplicate-column errors)
        if (!Schema::hasColumn('payments', 'currency')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('currency', 3)->default('NGN')->after('amount');
            });
        }

        if (!Schema::hasColumn('payments', 'status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status')->default('pending')->after('currency');
            });
        }

        if (!Schema::hasColumn('payments', 'flutterwave_tx_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('flutterwave_tx_id')->nullable()->after('reference');
            });
        }

        if (!Schema::hasColumn('payments', 'raw_response')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->json('raw_response')->nullable();
            });
        }

        if (!Schema::hasColumn('payments', 'paid_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable();
            });
        }
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
