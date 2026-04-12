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
        Schema::create('reporting_laundry_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laundry_order_id')->unique()->index();
            
            // Room/Guest info
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            
            // Timeline
            $table->timestamp('created_at')->index();
            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Metrics
            $table->integer('item_count')->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->boolean('was_refunded')->default(false);
            
            // Performance
            $table->integer('completion_minutes')->nullable();
            $table->integer('delay_minutes')->nullable();
            $table->boolean('sla_breach')->default(false);
            
            // Quality
            $table->string('status')->default('pending');
            $table->integer('complaint_count')->default(0);
            $table->integer('reopen_count')->default(0);
            $table->json('dispute_images')->nullable();
            
            $table->timestamps();

            $table->foreign('laundry_order_id')
                ->references('id')
                ->on('laundry_orders')
                ->cascadeOnDelete();

            $table->index(['payment_status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_laundry_facts');
    }
};
