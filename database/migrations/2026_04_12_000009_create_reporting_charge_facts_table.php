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
        Schema::create('reporting_charge_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('charge_id')->unique()->index();
            
            // Booking/Room context
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('room_id')->nullable()->index();
            
            // Charge origin
            $table->string('charge_type')->index(); // 'room_charge', 'order', 'laundry', 'service'
            $table->unsignedBigInteger('chargeble_id')->nullable(); // order_id, laundry_order_id, etc.
            $table->string('chargeable_type')->nullable();
            
            // User/Staff
            $table->unsignedBigInteger('posted_by_id')->nullable();
            $table->timestamp('posted_at')->index();
            
            // Financial
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('posted'); // 'pending', 'posted', 'refunded'
            
            // Reconciliation
            $table->decimal('payment_applied', 10, 2)->default(0);
            $table->decimal('outstanding', 10, 2);
            $table->boolean('refunded')->default(false);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            // Metadata
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            
            $table->timestamps();

            $table->foreign('charge_id')
                ->references('id')
                ->on('charges')
                ->cascadeOnDelete();

            $table->index(['posted_at', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index(['outstanding']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_charge_facts');
    }
};
