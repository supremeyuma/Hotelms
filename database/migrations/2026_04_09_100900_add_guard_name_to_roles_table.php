<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('guard_name')->default('web')->after('permissions');
            });
        }

        DB::table('roles')
            ->whereNull('guard_name')
            ->update(['guard_name' => 'web']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
        }
    }
};
