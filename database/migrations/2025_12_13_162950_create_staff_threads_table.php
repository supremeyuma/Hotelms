<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete(); // staff user
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete(); // admin who created
            $table->enum('type', ['query','commendation']);
            $table->string('title')->nullable(); // optional short title
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_threads');
    }
};
