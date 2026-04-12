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
        Schema::create('reporting_department_daily_facts', function (Blueprint $table) {
            $table->id();
            $table->string('department')->index();
            $table->date('date')->index();
            $table->unique(['department', 'date']);

            // Work metrics
            $table->integer('requests_received')->default(0);
            $table->integer('requests_completed')->default(0);
            $table->integer('requests_cancelled')->default(0);
            $table->integer('requests_escalated')->default(0);
            $table->integer('backlog_open')->default(0);

            // Performance metrics
            $table->integer('avg_response_minutes')->nullable();
            $table->integer('avg_completion_minutes')->nullable();
            $table->integer('sla_breaches')->default(0);

            // Financial impact
            $table->decimal('revenue', 10, 2)->nullable();
            $table->decimal('refunds', 10, 2)->nullable();
            $table->decimal('cost_of_consumption', 10, 2)->nullable();

            // Staff metrics
            $table->integer('staff_on_duty')->default(0);
            $table->integer('assignments_per_staff')->nullable();

            $table->timestamps();

            $table->index(['date', 'department']);
            $table->index(['department', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_department_daily_facts');
    }
};
