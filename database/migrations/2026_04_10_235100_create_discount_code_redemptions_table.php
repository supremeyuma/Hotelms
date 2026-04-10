<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->morphs('redeemable');
            $table->string('scope');
            $table->string('status')->default('reserved');
            $table->unsignedInteger('rooms_used')->default(0);
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->unique(['discount_code_id', 'redeemable_type', 'redeemable_id'], 'discount_code_redeemable_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_code_redemptions');
    }
};
