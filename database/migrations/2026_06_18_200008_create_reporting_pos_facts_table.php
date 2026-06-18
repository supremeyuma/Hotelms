<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_pos_facts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('fact_date');
            $table->foreignUlid('device_id')->nullable()->constrained('pos_devices')->nullOnDelete();
            $table->foreignUlid('session_id')->nullable()->constrained('pos_sessions')->nullOnDelete();
            $table->string('device_type', 30)->nullable();
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('cash_sales', 14, 2)->default(0);
            $table->decimal('card_sales', 14, 2)->default(0);
            $table->decimal('room_charge_sales', 14, 2)->default(0);
            $table->decimal('discounts_total', 14, 2)->default(0);
            $table->unsignedInteger('voids_count')->default(0);
            $table->decimal('voids_total', 14, 2)->default(0);
            $table->json('top_items')->nullable();
            $table->json('hourly_breakdown')->nullable();
            $table->timestamps();

            $table->unique('fact_date');
            $table->index('device_id');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_pos_facts');
    }
};
