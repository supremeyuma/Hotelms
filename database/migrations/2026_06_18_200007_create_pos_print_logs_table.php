<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_print_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('docket_id')->constrained('pos_dockets')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('printer_name', 100)->nullable();
            $table->text('content')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->boolean('success')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_print_logs');
    }
};
