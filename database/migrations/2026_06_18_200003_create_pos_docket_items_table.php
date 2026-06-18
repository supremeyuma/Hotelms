<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_docket_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('docket_id')->constrained('pos_dockets')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('item_name', 200);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index('docket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_docket_items');
    }
};
