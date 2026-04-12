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
        Schema::create('reporting_metric_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key')->unique()->index();
            $table->string('metric_name');
            $table->text('business_meaning');
            $table->string('calculation_type'); // 'aggregation', 'derived', 'snapshot'
            
            // Source tracking
            $table->json('source_tables');
            $table->text('transformation_rules')->nullable();
            $table->json('exclusions')->nullable();
            
            // Properties
            $table->string('time_basis')->default('occurrence'); // 'occurrence', 'posting', 'completion'
            $table->string('default_granularity')->default('daily'); // 'hourly', 'daily', 'weekly', 'monthly'
            $table->string('unit_of_measure')->nullable(); // 'count', 'currency', 'percentage', 'minutes'
            $table->string('owner')->nullable(); // department or role that owns this metric
            
            // Versioning
            $table->integer('version')->default(1);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            
            // Testing
            $table->json('test_cases')->nullable();
            $table->boolean('is_tested')->default(false);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->index(['owner', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_metric_definitions');
    }
};
