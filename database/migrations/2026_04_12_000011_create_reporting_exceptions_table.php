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
        Schema::create('reporting_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_type')->index(); // 'maintenance_overdue', 'laundry_overdue', 'kitchen_delayed', etc.
            $table->string('severity')->default('normal')->index(); // 'critical', 'high', 'normal', 'low'
            $table->string('department')->nullable()->index();
            
            // Reference info
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->unsignedBigInteger('booking_id')->nullable()->index();
            $table->string('reference_type')->nullable(); // 'MaintenanceTicket', 'LaundryOrder', 'Order'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            
            // Status tracking
            $table->string('status')->default('open')->index(); // 'open', 'acknowledged', 'resolved', 'ignored'
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            
            // Context
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('meta')->nullable(); // additional context
            
            // Escalation
            $table->boolean('escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->unsignedBigInteger('escalated_to_id')->nullable();
            
            // Resolution tracking
            $table->text('resolution_notes')->nullable();
            $table->string('follow_up_action')->nullable();
            
            $table->timestamps();

            $table->unique(['reference_type', 'reference_id', 'exception_type']);
            $table->index(['status', 'severity']);
            $table->index(['detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_exceptions');
    }
};
