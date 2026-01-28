<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cleaning_inventory_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            // Explicit short index name (IMPORTANT FOR MYSQL)
            $table->unique(
                ['room_type_id', 'inventory_item_id'],
                'clean_tpl_room_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_inventory_templates');
    }
};
