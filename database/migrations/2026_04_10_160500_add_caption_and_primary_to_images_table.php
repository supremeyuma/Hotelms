<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            if (! Schema::hasColumn('images', 'caption')) {
                $table->string('caption')->nullable()->after('path');
            }

            if (! Schema::hasColumn('images', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('caption');
                $table->index('is_primary');
            }
        });

        if (Schema::hasColumn('images', 'alt') && Schema::hasColumn('images', 'caption')) {
            DB::table('images')
                ->whereNull('caption')
                ->whereNotNull('alt')
                ->update(['caption' => DB::raw('alt')]);
        }

        if (Schema::hasColumn('images', 'is_primary')) {
            $groups = DB::table('images')
                ->select('imageable_type', 'imageable_id')
                ->groupBy('imageable_type', 'imageable_id')
                ->get();

            foreach ($groups as $group) {
                $hasPrimary = DB::table('images')
                    ->where('imageable_type', $group->imageable_type)
                    ->where('imageable_id', $group->imageable_id)
                    ->where('is_primary', true)
                    ->exists();

                if ($hasPrimary) {
                    continue;
                }

                $firstImageId = DB::table('images')
                    ->where('imageable_type', $group->imageable_type)
                    ->where('imageable_id', $group->imageable_id)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->value('id');

                if ($firstImageId) {
                    DB::table('images')->where('id', $firstImageId)->update(['is_primary' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            if (Schema::hasColumn('images', 'is_primary')) {
                $table->dropIndex(['is_primary']);
                $table->dropColumn('is_primary');
            }

            if (Schema::hasColumn('images', 'caption')) {
                $table->dropColumn('caption');
            }
        });
    }
};
