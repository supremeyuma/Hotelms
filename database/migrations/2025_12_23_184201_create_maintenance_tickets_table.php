<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table already exists (created by an earlier migration), skip
        if (Schema::hasTable('maintenance_tickets')) {
            return;
        }

        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->enum('status', ['open','closed'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tickets');
    }
};
