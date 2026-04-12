<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_tickets')) {
            return;
        }

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_tickets', 'staff_id')) {
                $table->foreignId('staff_id')
                    ->nullable()
                    ->after('room_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('maintenance_tickets', 'title')) {
                $table->string('title')->nullable()->after('staff_id');
            }

            if (! Schema::hasColumn('maintenance_tickets', 'meta')) {
                $table->json('meta')->nullable()->after('status');
            }

            if (! Schema::hasColumn('maintenance_tickets', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        if (Schema::hasColumn('maintenance_tickets', 'type') && Schema::hasColumn('maintenance_tickets', 'title')) {
            DB::table('maintenance_tickets')
                ->whereNull('title')
                ->update([
                    'title' => DB::raw("COALESCE(NULLIF(`type`, ''), 'Maintenance issue')"),
                ]);
        }

        if (
            Schema::hasColumn('maintenance_tickets', 'photo_path')
            && Schema::hasColumn('maintenance_tickets', 'meta')
        ) {
            $tickets = DB::table('maintenance_tickets')
                ->select('id', 'meta', 'photo_path')
                ->whereNotNull('photo_path')
                ->get();

            foreach ($tickets as $ticket) {
                $meta = [];

                if (is_string($ticket->meta) && $ticket->meta !== '') {
                    $decoded = json_decode($ticket->meta, true);
                    $meta = is_array($decoded) ? $decoded : [];
                } elseif (is_array($ticket->meta)) {
                    $meta = $ticket->meta;
                }

                if (! array_key_exists('photo_path', $meta) || blank($meta['photo_path'])) {
                    $meta['photo_path'] = $ticket->photo_path;
                }

                DB::table('maintenance_tickets')
                    ->where('id', $ticket->id)
                    ->update(['meta' => json_encode($meta)]);
            }
        }
    }

    public function down(): void
    {
        // This migration repairs schema drift on long-lived databases.
        // Reversing it safely would require knowing which columns were pre-existing.
    }
};
