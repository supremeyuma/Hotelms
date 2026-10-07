<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part B allocates a per-node monotonic sequence from here.
     *
     * Reading max(sequence) + 1 is a race: two concurrent writes on one node
     * read the same maximum, both mint the same value, and the unique
     * (node_id, sequence) index on sync_changes then turns a lost write into a
     * failed sale. Allocation needs a single locked counter row instead.
     *
     * SyncLogger::nextSequence() takes this row with lockForUpdate().
     */
    public function up(): void
    {
        Schema::create('sync_sequences', function (Blueprint $table) {
            $table->string('node_id', 64)->primary();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_sequences');
    }
};
