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
        Schema::create('reporting_staff_daily_facts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->string('department')->index();
            $table->date('date')->index();
            $table->unique(['staff_id', 'department', 'date']);

            // Work assignment metrics
            $table->integer('assignments_received')->default(0);
            $table->integer('assignments_completed')->default(0);
            $table->integer('assignments_reassigned')->default(0);
            $table->integer('open_work_end_of_day')->default(0);

            // Performance metrics
            $table->integer('avg_completion_minutes')->nullable();
            $table->integer('escalations')->default(0);

            // Financial impact
            $table->decimal('refunds_or_reversals', 10, 2)->nullable();
            $table->decimal('charges_posted', 10, 2)->nullable();
            $table->decimal('payments_collected', 10, 2)->nullable();

            // Quality metrics
            $table->integer('error_count')->default(0);
            $table->integer('repeat_requests')->default(0);

            $table->timestamps();

            $table->foreign('staff_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['date', 'department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_staff_daily_facts');
    }
};
