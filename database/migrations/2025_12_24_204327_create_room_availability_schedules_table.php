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
        Schema::create('room_availability_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->boolean('is_unavailable')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['room_id', 'start_date', 'end_date'], 'ra_sched_room_dates');
            $table->index(['room_type_id', 'start_date', 'end_date'], 'ra_sched_type_dates');
            $table->index(['property_id', 'start_date', 'end_date'], 'ra_sched_prop_dates');
            $table->index('is_unavailable', 'ra_sched_unavailable');
            
            $table->unique(['room_id', 'start_date', 'end_date'], 'unique_room_date_range');
            $table->unique(['room_type_id', 'start_date', 'end_date'], 'unique_room_type_date_range');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_availability_schedules');
    }
};