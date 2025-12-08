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
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('position')->nullable()->comment('Job title (e.g. receptionist)');
            $table->string('phone')->nullable()->index();
            $table->json('meta')->nullable()->comment('Custom JSON attributes');

            $table->string('action_code_hash')
                ->nullable()
                ->comment('Hashed staff action approval code');

            $table->timestamps();
            $table->softDeletes();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
