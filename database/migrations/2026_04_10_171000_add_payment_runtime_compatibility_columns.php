<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('reference');
            }

            if (! Schema::hasColumn('payments', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('payments', 'flutterwave_tx_status')) {
                $table->string('flutterwave_tx_status')->nullable()->after('flutterwave_tx_id');
            }
        });

        if (Schema::hasColumn('payments', 'payment_reference')) {
            DB::table('payments')
                ->whereNull('payment_reference')
                ->whereNotNull('reference')
                ->update(['payment_reference' => DB::raw('reference')]);
        }

        if (Schema::hasColumn('payments', 'amount_paid')) {
            DB::table('payments')
                ->whereNull('amount_paid')
                ->whereNotNull('amount')
                ->update(['amount_paid' => DB::raw('amount')]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'flutterwave_tx_status')) {
                $table->dropColumn('flutterwave_tx_status');
            }

            if (Schema::hasColumn('payments', 'amount_paid')) {
                $table->dropColumn('amount_paid');
            }

            if (Schema::hasColumn('payments', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });
    }
};
