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
        Schema::create('reporting_room_daily_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('room_id')->index();
            $table->date('date')->index();
            $table->unique(['room_id', 'date']);

            // Occupancy metrics
            $table->boolean('occupied')->default(false);
            $table->decimal('occupied_hours', 5, 2)->nullable();
            $table->integer('guest_count')->nullable();
            $table->integer('booking_count')->default(0);

            // Housekeeping
            $table->boolean('housekeeping_completed')->default(false);
            $table->integer('cleaning_duration_minutes')->nullable();

            // Maintenance
            $table->integer('maintenance_issue_count')->default(0);
            $table->integer('maintenance_open_count')->default(0);
            $table->boolean('out_of_service')->default(false);

            // Service orders
            $table->integer('kitchen_order_count')->default(0);
            $table->integer('bar_order_count')->default(0);
            $table->integer('laundry_request_count')->default(0);

            // Financial metrics
            $table->decimal('charges_posted', 10, 2)->default(0);
            $table->decimal('payments_received', 10, 2)->default(0);
            $table->decimal('refunds_issued', 10, 2)->default(0);
            $table->decimal('room_revenue', 10, 2)->default(0);

            // Guest experience
            $table->integer('complaints_count')->default(0);
            $table->integer('guest_requests_count')->default(0);

            $table->timestamps();

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->cascadeOnDelete();

            $table->index(['date', 'occupied']);
            $table->index(['room_id', 'occupied']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_room_daily_facts');
    }
};
