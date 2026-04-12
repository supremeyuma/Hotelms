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
        Schema::create('reporting_maintenance_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('maintenance_ticket_id')->unique()->index();
            
            // Room/Building info
            $table->unsignedBigInteger('room_id')->nullable()->index();
            $table->string('category')->index(); // 'plumbing', 'electrical', 'hvac', etc.
            
            // Priority/Severity
            $table->string('severity')->default('normal'); // 'urgent', 'normal', 'low'
            $table->string('status')->default('open')->index();
            
            // Timeline
            $table->timestamp('reported_at')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            
            // Ownership
            $table->unsignedBigInteger('assigned_staff_id')->nullable();
            $table->unsignedBigInteger('reported_by_id')->nullable();
            
            // Performance metrics
            $table->integer('response_minutes')->nullable();
            $table->integer('resolution_minutes')->nullable();
            $table->integer('reopen_count')->default(0);
            $table->boolean('sla_breach')->default(false);
            
            // Impact
            $table->boolean('room_out_of_service')->default(false);
            $table->integer('downtime_hours')->nullable();
            $table->boolean('escalated')->default(false);
            $table->string('escalation_reason')->nullable();
            
            // Cost
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            
            // Quality
            $table->string('closure_notes')->nullable();
            $table->integer('customer_satisfaction_score')->nullable();
            
            $table->timestamps();

            $table->foreign('maintenance_ticket_id')
                ->references('id')
                ->on('maintenance_tickets')
                ->cascadeOnDelete();

            $table->index(['status', 'reported_at']);
            $table->index(['room_out_of_service']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_maintenance_facts');
    }
};
