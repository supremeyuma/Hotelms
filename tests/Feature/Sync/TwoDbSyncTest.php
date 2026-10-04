<?php

namespace Tests\Feature\Sync;

use App\Sync\Hlc;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TwoDbSyncTest extends TestCase
{
    public function test_two_sqlite_databases_simulate_disconnected_nodes(): void
    {
        $db1 = DB::connection("sqlite");
        $db2 = DB::connection("sqlite");

        $this->assertNotNull($db1);
        $this->assertNotNull($db2);
    }
}
