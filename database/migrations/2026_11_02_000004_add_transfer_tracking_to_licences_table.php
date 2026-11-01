<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service moves between PCs.
 *
 * A licence is bound to one machine, but hotels do replace hardware. The
 * customer can move it themselves a limited number of times per term; beyond
 * that support takes over. The counter lives on the licence because a renewal is
 * a new licence, which naturally resets the allowance for the new year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licences', function (Blueprint $table) {
            $table->unsignedSmallInteger('transfer_count')->default(0)->after('machine_id');
            $table->string('previous_machine_id')->nullable()->after('transfer_count');
            $table->timestamp('last_transferred_at')->nullable()->after('previous_machine_id');
        });

        // One row per move, so support can see where a key has been and when.
        Schema::create('licence_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licence_id')->constrained('licences')->cascadeOnDelete();
            $table->string('from_machine_id')->nullable();
            $table->string('to_machine_id');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['licence_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_transfers');

        Schema::table('licences', function (Blueprint $table) {
            $table->dropColumn(['transfer_count', 'previous_machine_id', 'last_transferred_at']);
        });
    }
};
