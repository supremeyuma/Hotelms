<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('menu_subcategories', 'prep_time_minutes')) {
            Schema::table('menu_subcategories', function (Blueprint $table) {
                $table->unsignedInteger('prep_time_minutes')->nullable()->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_subcategories', 'prep_time_minutes')) {
            Schema::table('menu_subcategories', function (Blueprint $table) {
                $table->dropColumn('prep_time_minutes');
            });
        }
    }
};
