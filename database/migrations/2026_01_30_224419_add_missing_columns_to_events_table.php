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
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
            $table->string('venue')->nullable();
            $table->integer('capacity')->nullable();
            $table->datetime('start_datetime')->nullable();
            $table->datetime('end_datetime')->nullable();
            $table->datetime('ticket_sales_start')->nullable();
            $table->datetime('ticket_sales_end')->nullable();
            $table->integer('max_tickets_per_person')->default(10);
            $table->boolean('has_table_reservations')->default(false);
            $table->integer('table_capacity')->nullable();
            $table->decimal('table_price', 8, 2)->nullable();
            $table->json('promotional_content')->nullable();
            $table->string('status')->default('draft');
            $table->softDeletes();
            
            // Add indexes
            $table->index('is_featured');
            $table->index('is_active');
            $table->index('status');
            $table->index('start_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'is_featured',
                'venue',
                'capacity',
                'start_datetime',
                'end_datetime',
                'ticket_sales_start',
                'ticket_sales_end',
                'max_tickets_per_person',
                'has_table_reservations',
                'table_capacity',
                'table_price',
                'promotional_content',
                'status',
                'deleted_at'
            ]);
        });
    }
};
