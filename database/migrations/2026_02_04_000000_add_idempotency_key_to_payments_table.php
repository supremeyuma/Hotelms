<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add idempotency key to payments table
     */
    public function up(): void
    {
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('idempotency_key')->nullable()->after('flutterwave_refund_id');
                $table->unique('idempotency_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
