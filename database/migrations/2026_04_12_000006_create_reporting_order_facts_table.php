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
        Schema::create('reporting_order_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique()->index();
            
            // Service area
            $table->string('service_area')->index(); // 'kitchen', 'bar', 'room_service'
            
            // Timestamps
            $table->timestamp('created_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Order details
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('staff_owner_id')->nullable();
            
            // Financial
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('payment_status')->default('pending'); // 'pending', 'paid', 'cancelled'
            $table->boolean('was_refunded')->default(false);
            $table->decimal('refund_amount', 10, 2)->nullable();
            
            // Performance metrics
            $table->integer('completion_minutes')->nullable();
            $table->integer('delay_minutes')->nullable();
            $table->integer('delay_severity')->nullable(); // 1=minor, 2=significant, 3=critical
            
            // Quality flags
            $table->string('status')->default('pending');
            $table->integer('reopen_count')->default(0);
            $table->boolean('sla_breached')->default(false);
            
            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            $table->index(['service_area', 'created_at']);
            $table->index(['payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_order_facts');
    }
};
