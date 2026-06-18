<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('device_code', 20)->unique();
            $table->string('device_name', 100);
            $table->string('device_type', 20)->default('club_terminal');
            $table->string('api_token', 64)->unique()->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_devices');
    }
};
