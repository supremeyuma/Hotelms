<?php

// database/migrations/2025_12_13_000003_create_staff_thread_messages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_thread_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('staff_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete(); // admin or staff
            $table->text('message');
            $table->json('attachments')->nullable(); // for images or files
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_thread_messages');
    }
};
