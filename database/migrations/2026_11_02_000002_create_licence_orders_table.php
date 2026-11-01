<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A licence purchase in progress.
 *
 * Kept separate from `payments`: a licence sale is not hotel revenue, must not
 * reach the hotel's ledgers or reports, and is resolved by issuing a licence
 * rather than by marking a booking paid. It is also the record that makes the
 * gateway callback and the webhook safe to run twice — whichever arrives first
 * completes the order and issues the licence; the second sees `paid` and stops.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_orders', function (Blueprint $table) {
            $table->id();

            // Sent to the gateway as its transaction reference.
            $table->string('reference')->unique();

            $table->foreignId('licence_plan_id')->nullable()->constrained('licence_plans')->nullOnDelete();

            $table->string('property_name');
            $table->unsignedSmallInteger('rooms');
            $table->string('customer_name')->nullable();
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            // Snapshot of the price at checkout: the plan may be repriced later,
            // and an unpaid order must still complete at the amount charged.
            $table->char('currency', 3);
            $table->decimal('monthly_amount', 12, 2);
            $table->unsignedSmallInteger('term_months');
            $table->decimal('amount', 12, 2);

            $table->string('provider')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider_reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('licence_id')->nullable()->constrained('licences')->nullOnDelete();

            // The issued key, encrypted with APP_KEY, held only until the buyer
            // opens the success page (which reveals it once and clears this).
            // The webhook can complete the order before the browser callback, so
            // without this the buyer could pay and never see the key.
            $table->text('key_ciphertext')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_orders');
    }
};
