<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drink_stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('drink_stock_id')->constrained('drink_stock')->cascadeOnDelete();
            $table->string('type', 30);
            $table->integer('quantity_change');
            $table->unsignedInteger('full_bottles_before');
            $table->unsignedInteger('full_bottles_after');
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drink_stock_movements');
    }
};
