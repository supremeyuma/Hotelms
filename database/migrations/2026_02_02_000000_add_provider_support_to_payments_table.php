<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add multi-provider payment support fields to payments and related tables
     */
    public function up(): void
    {
        // Add provider column to payments table
        if (!Schema::hasColumn('payments', 'provider')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('provider')->default('flutterwave')->after('status');
                $table->index('provider');
            });
        }

        // Add external_reference for non-Flutterwave transactions
        if (!Schema::hasColumn('payments', 'external_reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('external_reference')->nullable()->after('flutterwave_tx_id');
                $table->index('external_reference');
            });
        }

        // Add verified_at for tracking payment verification timestamp
        if (!Schema::hasColumn('payments', 'verified_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('verified_at')->nullable()->after('paid_at');
            });
        }

        // Add payment_type to distinguish between booking, order, ticket, etc.
        if (!Schema::hasColumn('payments', 'payment_type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_type')->nullable()->after('provider');
                $table->index('payment_type');
            });
        }

        // Add provider support to event_tickets
        if (Schema::hasTable('event_tickets')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('event_tickets', 'payment_provider')) {
                    $table->string('payment_provider')->nullable()->after('status');
                }

                if (!Schema::hasColumn('event_tickets', 'payment_reference')) {
                    $table->string('payment_reference')->nullable()->after('payment_provider');
                }

                if (!Schema::hasColumn('event_tickets', 'payment_verified_at')) {
                    $table->timestamp('payment_verified_at')->nullable()->after('payment_reference');
                }
            });
        }

        // Add provider support to event_table_reservations
        if (Schema::hasTable('event_table_reservations')) {
            Schema::table('event_table_reservations', function (Blueprint $table) {
                if (!Schema::hasColumn('event_table_reservations', 'payment_provider')) {
                    $table->string('payment_provider')->nullable()->after('status');
                }

                if (!Schema::hasColumn('event_table_reservations', 'payment_reference')) {
                    $table->string('payment_reference')->nullable()->after('payment_provider');
                }

                if (!Schema::hasColumn('event_table_reservations', 'payment_verified_at')) {
                    $table->timestamp('payment_verified_at')->nullable()->after('payment_reference');
                }
            });
        }

        // Add provider support to bookings if not exists
        if (!Schema::hasColumn('bookings', 'payment_method')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('payment_method')->nullable()->after('payment_status');
                $table->index('payment_method');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropIndex(['external_reference']);
            $table->dropIndex(['payment_type']);
            $table->dropColumn([
                'provider',
                'external_reference',
                'verified_at',
                'payment_type',
            ]);
        });

        Schema::table('event_tickets', function (Blueprint $table) {
            $table->dropIndex(['payment_provider']);
            $table->dropIndex(['payment_reference']);
            $table->dropColumn([
                'payment_provider',
                'payment_reference',
                'payment_verified_at',
            ]);
        });

        Schema::table('event_table_reservations', function (Blueprint $table) {
            $table->dropIndex(['payment_provider']);
            $table->dropIndex(['payment_reference']);
            $table->dropColumn([
                'payment_provider',
                'payment_reference',
                'payment_verified_at',
            ]);
        });

        if (Schema::hasColumn('bookings', 'payment_method')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropIndex(['payment_method']);
                $table->dropColumn('payment_method');
            });
        }
    }
};
