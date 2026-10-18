<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('docket_id')->constrained('pos_dockets')->cascadeOnDelete();
            $table->string('node_id');
            $table->boolean('is_reprint')->default(false);
            $table->string('status')->default('queued'); // queued | sent | done | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            $table->index(['node_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
