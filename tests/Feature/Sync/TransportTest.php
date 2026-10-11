<?php

namespace Tests\Feature\Sync;

use App\Sync\Exceptions\SyncTransportException;
use App\Sync\HttpSyncTransport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransportTest extends TestCase
{
    public function test_push_posts_a_batched_change_array(): void
    {
        Http::fake([
            '*/api/sync/changes' => Http::response(['accepted' => 2], 200),
        ]);

        config(['sync.credential' => 'secret']);

        $result = app(HttpSyncTransport::class)->push('http://peer', [
            ['table_name' => 'pos_dockets', 'row_id' => '1', 'operation' => 'create'],
            ['table_name' => 'pos_dockets', 'row_id' => '2', 'operation' => 'create'],
        ]);

        $this->assertSame(2, $result['accepted']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://peer/api/sync/changes'
                && count($request->data()['changes']) === 2;
        });
    }

    public function test_push_sends_the_node_id_and_credential(): void
    {
        Http::fake(['*/api/sync/changes' => Http::response(['accepted' => 0])]);

        config([
            'sync.node_id'    => 'node-a',
            'sync.credential' => 'secret-token',
        ]);

        app(HttpSyncTransport::class)->push('http://peer', []);

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Sync-Node-Id', 'node-a')
            && $r->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_a_peer_entry_credential_overrides_the_node_credential(): void
    {
        Http::fake(['*/api/sync/changes' => Http::response(['accepted' => 0])]);

        config([
            'sync.node_id'    => 'node-a',
            'sync.credential' => 'node-secret',
            'sync.peers'      => [
                'node-b' => ['base_url' => 'http://peer', 'credential' => 'peer-secret'],
            ],
        ]);

        app(HttpSyncTransport::class)->push('http://peer', []);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer peer-secret'));
    }

    public function test_pull_returns_the_changes_and_a_cursor(): void
    {
        Http::fake([
            '*/api/sync/changes*' => Http::response([
                'changes'  => [['table_name' => 'inventory_items', 'row_id' => 'X', 'operation' => 'create']],
                'cursor'   => 7,
                'has_more' => true,
            ]),
        ]);

        $result = app(HttpSyncTransport::class)->pull('http://peer', 3);

        $this->assertCount(1, $result['changes']);
        $this->assertSame(7, $result['cursor']);
        $this->assertTrue($result['has_more']);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://peer/api/sync/changes?since=3&limit=500');
    }

    public function test_an_unreachable_peer_raises_a_transport_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->expectException(SyncTransportException::class);

        app(HttpSyncTransport::class)->push('http://down', []);
    }

    public function test_a_500_response_raises_a_transport_exception(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->expectException(SyncTransportException::class);

        app(HttpSyncTransport::class)->push('http://peer', []);
    }
}
