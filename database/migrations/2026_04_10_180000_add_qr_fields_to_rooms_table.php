<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'qr_key')) {
                $table->string('qr_key', 64)->nullable()->unique()->after('floor');
            }

            if (! Schema::hasColumn('rooms', 'qr_generated_at')) {
                $table->timestamp('qr_generated_at')->nullable()->after('qr_key');
            }

            if (! Schema::hasColumn('rooms', 'qr_invalidated_at')) {
                $table->timestamp('qr_invalidated_at')->nullable()->after('qr_generated_at');
            }
        });

        $roomIds = DB::table('rooms')
            ->whereNull('deleted_at')
            ->whereNull('qr_key')
            ->pluck('id');

        foreach ($roomIds as $roomId) {
            DB::table('rooms')
                ->where('id', $roomId)
                ->update([
                    'qr_key' => Str::random(48),
                    'qr_generated_at' => now(),
                    'qr_invalidated_at' => null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'qr_invalidated_at')) {
                $table->dropColumn('qr_invalidated_at');
            }

            if (Schema::hasColumn('rooms', 'qr_generated_at')) {
                $table->dropColumn('qr_generated_at');
            }

            if (Schema::hasColumn('rooms', 'qr_key')) {
                $table->dropUnique('rooms_qr_key_unique');
                $table->dropColumn('qr_key');
            }
        });
    }
};
