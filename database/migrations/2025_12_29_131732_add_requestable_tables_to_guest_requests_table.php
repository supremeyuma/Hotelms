<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guest_requests', function (Blueprint $table) {
            // Polymorphic relationship to any service order
            $table->string('requestable_type')->nullable()->after('id');
            $table->unsignedBigInteger('requestable_id')->nullable()->after('requestable_type');

            // Normalize type + status usage
            $table->string('type')->index()->change();
            $table->string('status')->index()->change();

            // Useful for FrontDesk visibility + auditing
            $table->timestamp('acknowledged_at')->nullable()->after('status');

            // Performance indexes
            $table->index(['requestable_type', 'requestable_id'], 'guest_requests_requestable_index');
            $table->index(['type', 'status'], 'guest_requests_type_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('guest_requests', function (Blueprint $table) {
            $table->dropIndex('guest_requests_requestable_index');
            $table->dropIndex('guest_requests_type_status_index');

            $table->dropColumn([
                'requestable_type',
                'requestable_id',
                'acknowledged_at',
            ]);
        });
    }
};
