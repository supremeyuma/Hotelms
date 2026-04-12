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
        Schema::create('reporting_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('event_type')->index(); // e.g., 'booking.confirmed', 'guest.checked_in'
            $table->string('domain')->index(); // e.g., 'operations', 'finance', 'service'
            $table->string('department')->nullable()->index(); // e.g., 'kitchen', 'housekeeping'
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->unsignedBigInteger('guest_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // actor
            $table->string('reference_type')->nullable(); // 'Order', 'MaintenanceTicket', 'LaundryOrder'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable()->default('USD');
            $table->json('meta')->nullable(); // additional context
            $table->timestamps();

            $table->fullText(['event_type', 'domain', 'department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_events');
    }
};
