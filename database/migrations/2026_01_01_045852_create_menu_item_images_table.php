<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if table already exists (could have been created by another migration)
        if (Schema::hasTable('menu_item_images')) {
            return;
        }

        Schema::create('menu_item_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_item_id');
            $table->string('path');
            $table->timestamps();
        });

        // Add foreign key only if the referenced `menu_items` table exists
        if (Schema::hasTable('menu_items')) {
            Schema::table('menu_item_images', function (Blueprint $table) {
                $table->foreign('menu_item_id')->references('id')->on('menu_items')->cascadeOnDelete();
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_images');
    }
};
