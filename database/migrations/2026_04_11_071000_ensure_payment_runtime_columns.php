<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'room_id')) {
                $table->foreignId('room_id')->nullable()->after('booking_id');
            }

            if (! Schema::hasColumn('payments', 'currency')) {
                $table->string('currency', 3)->default('NGN')->after('amount_paid');
            }

            if (! Schema::hasColumn('payments', 'reference')) {
                $table->string('reference')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('payments', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('reference');
            }

            if (! Schema::hasColumn('payments', 'flutterwave_tx_id')) {
                $table->string('flutterwave_tx_id')->nullable()->after('payment_reference');
            }

            if (! Schema::hasColumn('payments', 'flutterwave_tx_status')) {
                $table->string('flutterwave_tx_status')->nullable()->after('flutterwave_tx_id');
            }

            if (! Schema::hasColumn('payments', 'external_reference')) {
                $table->string('external_reference')->nullable()->after('flutterwave_tx_status');
            }

            if (! Schema::hasColumn('payments', 'method')) {
                $table->string('method')->nullable()->after('amount');
            }

            if (! Schema::hasColumn('payments', 'provider')) {
                $table->string('provider')->nullable()->after('status');
            }

            if (! Schema::hasColumn('payments', 'payment_type')) {
                $table->string('payment_type')->nullable()->after('provider');
            }

            if (! Schema::hasColumn('payments', 'transaction_ref')) {
                $table->string('transaction_ref')->nullable()->after('payment_type');
            }

            if (! Schema::hasColumn('payments', 'meta')) {
                $table->json('meta')->nullable()->after('transaction_ref');
            }

            if (! Schema::hasColumn('payments', 'raw_response')) {
                $table->json('raw_response')->nullable()->after('meta');
            }

            if (! Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('raw_response');
            }

            if (! Schema::hasColumn('payments', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('paid_at');
            }

            if (! Schema::hasColumn('payments', 'flutterwave_tx_ref')) {
                $table->string('flutterwave_tx_ref')->nullable()->after('verified_at');
            }

            if (! Schema::hasColumn('payments', 'flutterwave_refund_id')) {
                $table->string('flutterwave_refund_id')->nullable()->after('flutterwave_tx_ref');
            }

            if (! Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('flutterwave_refund_id');
            }

            if (! Schema::hasColumn('payments', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('idempotency_key');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left non-destructive for production safety.
    }
};
