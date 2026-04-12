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
        Schema::create('reporting_payment_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->unique()->index();
            
            // Booking/Room context
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('room_id')->nullable()->index();
            
            // Payment details
            $table->string('payment_method')->index(); // 'cash', 'card', 'transfer', 'mobile_money'
            $table->string('provider')->nullable(); // 'flutterwave', 'paystack'
            $table->string('transaction_id')->nullable();
            
            // User/Staff
            $table->unsignedBigInteger('collected_by_id')->nullable();
            $table->timestamp('paid_at')->index();
            
            // Financial
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('completed'); // 'pending', 'completed', 'failed', 'reversed'
            
            // Reconciliation
            $table->decimal('charges_applied', 10, 2)->default(0);
            $table->unsignedBigInteger('charges_count')->default(0);
            $table->boolean('overpayment_flag')->default(false);
            $table->decimal('overpayment_amount', 10, 2)->nullable();
            
            // Reversal tracking
            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            
            // Metadata
            $table->string('receipt_number')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamps();

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->cascadeOnDelete();

            $table->index(['paid_at', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index(['payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_payment_facts');
    }
};
