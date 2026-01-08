<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL requires raw SQL to modify ENUM safely
        DB::statement("
            ALTER TABLE orders
            MODIFY status VARCHAR(30) NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE orders
            MODIFY status ENUM(
                'pending',
                'preparing',
                'ready',
                'delivered'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
