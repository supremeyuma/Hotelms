<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncChange;
use App\Sync\Hlc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_hlc_works(): void
    {
        $hlc = Hlc::now("node1");
        $this->assertEquals("node1", $hlc->nodeId);
        $s = $hlc->toString();
        $parsed = Hlc::fromString($s);
        $this->assertEquals($hlc->nodeId, $parsed->nodeId);
    }

    public function test_syncable_trait_on_sync_change(): void
    {
        $change = SyncChange::create([
            "table_name" => "test",
            "row_id" => 1,
            "operation" => "create",
            "node_id" => "n1",
            "hlc" => Hlc::now("n1")->toString(),
        ]);
        $this->assertEquals("sync_changes", $change->getSyncTable());
        $this->assertNotEmpty($change->getSyncId());
    }
}
