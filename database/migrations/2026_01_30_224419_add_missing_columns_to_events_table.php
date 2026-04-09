<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }

            if (!Schema::hasColumn('events', 'venue')) {
                $table->string('venue')->nullable();
            }

            if (!Schema::hasColumn('events', 'capacity')) {
                $table->integer('capacity')->nullable();
            }

            if (!Schema::hasColumn('events', 'start_datetime')) {
                $table->datetime('start_datetime')->nullable();
            }

            if (!Schema::hasColumn('events', 'end_datetime')) {
                $table->datetime('end_datetime')->nullable();
            }

            if (!Schema::hasColumn('events', 'ticket_sales_start')) {
                $table->datetime('ticket_sales_start')->nullable();
            }

            if (!Schema::hasColumn('events', 'ticket_sales_end')) {
                $table->datetime('ticket_sales_end')->nullable();
            }

            if (!Schema::hasColumn('events', 'max_tickets_per_person')) {
                $table->integer('max_tickets_per_person')->default(10);
            }

            if (!Schema::hasColumn('events', 'has_table_reservations')) {
                $table->boolean('has_table_reservations')->default(false);
            }

            if (!Schema::hasColumn('events', 'table_capacity')) {
                $table->integer('table_capacity')->nullable();
            }

            if (!Schema::hasColumn('events', 'table_price')) {
                $table->decimal('table_price', 8, 2)->nullable();
            }

            if (!Schema::hasColumn('events', 'promotional_content')) {
                $table->json('promotional_content')->nullable();
            }

            if (!Schema::hasColumn('events', 'status')) {
                $table->string('status')->default('draft');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (! $this->hasIndex('events', 'events_is_featured_index')) {
                $table->index('is_featured');
            }

            if (! $this->hasIndex('events', 'events_is_active_index')) {
                $table->index('is_active');
            }

            if (! $this->hasIndex('events', 'events_status_index')) {
                $table->index('status');
            }

            if (! $this->hasIndex('events', 'events_start_datetime_index')) {
                $table->index('start_datetime');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if ($this->hasIndex('events', 'events_is_featured_index')) {
                $table->dropIndex('events_is_featured_index');
            }

            if ($this->hasIndex('events', 'events_is_active_index')) {
                $table->dropIndex('events_is_active_index');
            }

            if ($this->hasIndex('events', 'events_status_index')) {
                $table->dropIndex('events_status_index');
            }

            if ($this->hasIndex('events', 'events_start_datetime_index')) {
                $table->dropIndex('events_start_datetime_index');
            }
        });

        $columns = array_values(array_filter([
            Schema::hasColumn('events', 'is_featured') ? 'is_featured' : null,
            Schema::hasColumn('events', 'venue') ? 'venue' : null,
            Schema::hasColumn('events', 'capacity') ? 'capacity' : null,
            Schema::hasColumn('events', 'start_datetime') ? 'start_datetime' : null,
            Schema::hasColumn('events', 'end_datetime') ? 'end_datetime' : null,
            Schema::hasColumn('events', 'ticket_sales_start') ? 'ticket_sales_start' : null,
            Schema::hasColumn('events', 'ticket_sales_end') ? 'ticket_sales_end' : null,
            Schema::hasColumn('events', 'max_tickets_per_person') ? 'max_tickets_per_person' : null,
            Schema::hasColumn('events', 'has_table_reservations') ? 'has_table_reservations' : null,
            Schema::hasColumn('events', 'table_capacity') ? 'table_capacity' : null,
            Schema::hasColumn('events', 'table_price') ? 'table_price' : null,
            Schema::hasColumn('events', 'promotional_content') ? 'promotional_content' : null,
            Schema::hasColumn('events', 'status') ? 'status' : null,
        ]));

        if ($columns !== []) {
            Schema::table('events', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexExists = DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        return $indexExists;
    }
};
