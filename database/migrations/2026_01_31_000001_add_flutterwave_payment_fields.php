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
        // Add payment fields to orders table if not present
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_method')->default('postpaid')->after('total'); // offline/online
                $table->string('payment_status')->default('not_required')->after('payment_method'); // not_required/pending/paid/failed
                $table->string('payment_reference')->nullable()->after('payment_status');
            });
        }

        // Add payment fields to bookings table if not present
        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'payment_method')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('payment_method')->default('offline')->after('status'); // offline/online
                $table->string('payment_status')->default('pending')->after('payment_method'); // pending/paid/failed
            });
        }

        // Add additional Flutterwave fields to payments table if not present
        // (Note: basic flutterwave fields were already added in migration 2026_01_08_164656_add_flutterwave_fields_to_payments_table)
        if (Schema::hasTable('payments')) {
            if (!Schema::hasColumn('payments', 'flutterwave_tx_ref')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->string('flutterwave_tx_ref')->nullable()->index();
                });
            }
            if (!Schema::hasColumn('payments', 'flutterwave_refund_id')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->string('flutterwave_refund_id')->nullable();
                });
            }
            if (!Schema::hasColumn('payments', 'refunded_at')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->datetime('refunded_at')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'payment_method')) $table->dropColumn('payment_method');
                if (Schema::hasColumn('orders', 'payment_status')) $table->dropColumn('payment_status');
                if (Schema::hasColumn('orders', 'payment_reference')) $table->dropColumn('payment_reference');
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (Schema::hasColumn('bookings', 'payment_method')) $table->dropColumn('payment_method');
                if (Schema::hasColumn('bookings', 'payment_status')) $table->dropColumn('payment_status');
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (Schema::hasColumn('payments', 'flutterwave_tx_ref')) $table->dropColumn('flutterwave_tx_ref');
                if (Schema::hasColumn('payments', 'flutterwave_tx_id')) $table->dropColumn('flutterwave_tx_id');
                if (Schema::hasColumn('payments', 'flutterwave_tx_status')) $table->dropColumn('flutterwave_tx_status');
                if (Schema::hasColumn('payments', 'flutterwave_refund_id')) $table->dropColumn('flutterwave_refund_id');
                if (Schema::hasColumn('payments', 'raw_response')) $table->dropColumn('raw_response');
                if (Schema::hasColumn('payments', 'refunded_at')) $table->dropColumn('refunded_at');
            });
        }
    }
};
