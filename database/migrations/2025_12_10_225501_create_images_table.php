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

            // Polymorphic relation
            $table->unsignedBigInteger('imageable_id')->index();
            $table->string('imageable_type')->index();

            // File storage path (relative to storage disk)
            $table->string('path')->comment('Storage path for the image file');

            $table->string('caption')->nullable()->comment('Optional caption for the image');
            $table->boolean('is_primary')->default(false)->index()->comment('Whether this is the primary image for the model');

            $table->json('meta')->nullable()->comment('Optional JSON meta (source, photographer, alt text, etc.)');

            $table->timestamps();
            $table->softDeletes();

            // Compound index for fast polymorphic primary lookups
            $table->index(['imageable_type', 'imageable_id', 'is_primary'], 'images_imageable_primary_idx');
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
