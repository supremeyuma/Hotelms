<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_print_logs', function (Blueprint $table) {
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_reprint')->default(false);
            $table->string('reason', 100)->nullable();
            $table->string('node_id')->nullable();
            $table->index('node_id');
        });
    }

    public function down(): void
    {
        Schema::table('pos_print_logs', function (Blueprint $table) {
            $table->dropIndex(['node_id']);
            $table->dropConstrainedForeignId('printed_by');
            $table->dropColumn(['is_reprint', 'reason', 'node_id']);
        });
    }
};
