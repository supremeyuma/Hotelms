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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            
            $table->morphs('imageable'); // imageable_id + imageable_type
            
            $table->string('path')->comment('File path');
            $table->string('alt')->nullable()->comment('Alt text');
            $table->json('meta')->nullable()->comment('Dimension, size');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
