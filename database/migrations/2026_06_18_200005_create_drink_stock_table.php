<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drink_stock', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('menu_item_id')->unique();
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('full_bottles')->default(0);
            $table->unsignedInteger('opened_bottles')->default(0);
            $table->unsignedInteger('pours_per_bottle')->default(25);
            $table->unsignedInteger('low_stock_threshold')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drink_stock');
    }
};
