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
        Schema::create('reporting_booking_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->unique()->index();
            
            // Booking info
            $table->string('booking_source')->nullable(); // 'direct', 'booking.com', etc.
            $table->string('status_lifecycle')->nullable(); // 'pending,confirmed,checked_in,checked_out'
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->date('actual_check_in')->nullable();
            $table->date('actual_check_out')->nullable();
            
            // Stay metrics
            $table->integer('room_nights')->default(0);
            $table->integer('guest_count')->default(0);
            $table->integer('room_count')->default(0);
            
            // Financial metrics
            $table->decimal('room_revenue', 10, 2)->default(0);
            $table->decimal('ancillary_revenue', 10, 2)->default(0);
            $table->decimal('total_charges', 10, 2)->default(0);
            $table->decimal('total_payments', 10, 2)->default(0);
            $table->decimal('outstanding_balance', 10, 2)->default(0);
            
            // Service experience
            $table->integer('complaints_count')->default(0);
            $table->integer('service_requests_count')->default(0);
            $table->boolean('checkout_delay')->default(false);
            $table->boolean('checkin_delay')->default(false);
            
            // Special flags
            $table->boolean('requires_follow_up')->default(false);
            $table->json('tags')->nullable(); // e.g., ['vip', 'complaint_pending']
            
            $table->timestamps();

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();

            $table->index(['check_in_date', 'check_out_date']);
            $table->index(['outstanding_balance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_booking_facts');
    }
};
