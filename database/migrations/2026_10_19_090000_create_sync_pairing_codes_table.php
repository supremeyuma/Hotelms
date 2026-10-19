<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pairing codes are the one pre-credential entry point into the node
     * registry: a terminal exchanges a code for its node credential. The code
     * is single-use (used_at), time-limited (expires_at) and bound to one
     * property, so a leaked code cannot register a node for anyone else.
     *
     * Fresh timestamp on purpose: the plan's 2026_10_05_100080 collides with
     * the existing 2026_10_05_* sync migration family.
     */
    public function up(): void
    {
        Schema::create('sync_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id');

            // Declared before the foreign key so MySQL reuses it for the FK
            // instead of adding a second index; postgres needs it outright
            // because foreign keys are not auto-indexed there.
            $table->index('property_id');
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();

            // The unique index serves the redemption lookup (code equality is
            // always part of every redemption WHERE clause).
            $table->string('code', 32)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_pairing_codes');
    }
};
