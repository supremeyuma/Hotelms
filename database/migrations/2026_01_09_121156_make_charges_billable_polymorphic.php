<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            // Drop the broken FK
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');

            // Add polymorphic relation
            $table->nullableMorphs('billable');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropMorphs('billable');

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
        });
    }
};
