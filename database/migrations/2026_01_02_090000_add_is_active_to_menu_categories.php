<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('menu_categories', 'is_active')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menu_categories', 'is_active')) {
            Schema::table('menu_categories', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
