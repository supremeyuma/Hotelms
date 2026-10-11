<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use Illuminate\Support\Facades\DB;

/**
 * One synchronization cycle against one peer.
 *
 * Delivery is expressed entirely by sync_cursors, in two directions:
 *
 *  - inbound  ("in")  - how far this node has applied changes FROM the peer.
 *  - outbound ("out") - how far this node has pushed its own log TO the peer.
 *
 * The outbound cursor is what makes push incremental. Without it a push can
 * only ever send the first batch, because nothing records what the peer has
 * already been given. It is advanced only after the peer confirms the batch,
 * so a failed push is retried rather than silently dropped.
 */
class NodeSyncService
{
    public function __construct(
        private readonly HttpSyncTransport $transport,
        private readonly SyncApplier $applier,
    ) {
    }

    /**
     * Synchronise with one peer.
     *
     * @param  string  $peerId   The peer's node id, used for cursor bookkeeping.
     * @param  string  $baseUrl  Where the peer is reachable.
     * @return array{pushed: int, pulled: int, cursor: int}
     */
    public function syncWith(string $peerId, string $baseUrl): array
    {
        $pushed = $this->pushLocalChanges($peerId, $baseUrl);
        [$pulled, $cursor] = $this->pullRemoteChanges($peerId, $baseUrl);

        return ['pushed' => $pushed, 'pulled' => $pulled, 'cursor' => $cursor];
    }

    /**
     * Push only, for the `sync:push` command.
     */
    public function pushOnly(string $peerId, string $baseUrl): int
    {
        return $this->pushLocalChanges($peerId, $baseUrl);
    }

    /**
     * Pull only, for the `sync:pull` command.
     *
     * @return array{0: int, 1: int}  [pulled count, cursor]
     */
    public function pullOnly(string $peerId, string $baseUrl): array
    {
        return $this->pullRemoteChanges($peerId, $baseUrl);
    }

    /**
     * Push our own changes the peer has not seen, in pages.
     */
    private function pushLocalChanges(string $peerId, string $baseUrl): int
    {
        $self = (string) config('sync.node_id');
        $batch = (int) config('sync.batch_size');
        $cursor = $this->outboundCursor($peerId);
        $pushed = 0;

        while (true) {
            $changes = SyncChange::query()
                ->where('node_id', $self)
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit($batch)
                ->get();

            if ($changes->isEmpty()) {
                break;
            }

            $result = $this->transport->push($baseUrl, $changes->map(fn (SyncChange $c) => [
                'change_id'  => $c->id,
                'table_name' => $c->table_name,
                'row_id'     => (string) $c->row_id,
                'operation'  => $c->operation,
                'node_id'    => $c->node_id,
                'hlc'        => $c->hlc,
                'payload'    => $c->payload,
            ])->all());

            if ($result['accepted'] <= 0) {
                break;
            }

            $cursor = (int) $changes->last()->id;
            $this->advanceOutboundCursor($peerId, $cursor);
            $pushed += $result['accepted'];

            if ($changes->count() < $batch) {
                break;
            }
        }

        return $pushed;
    }

    /**
     * Pull and apply in pages, advancing the cursor only after each page
     * commits. Advancing first loses changes if the process dies mid-page.
     *
     * @return array{0: int, 1: int}
     */
    private function pullRemoteChanges(string $peerId, string $baseUrl): array
    {
        $batch = (int) config('sync.batch_size');
        $cursor = $this->inboundCursor($peerId);
        $pulled = 0;

        do {
            $page = $this->transport->pull($baseUrl, $cursor, $batch);

            if ($page['changes'] === []) {
                break;
            }

            // Applying the page and advancing the cursor share one
            // transaction: a crash rolls both back, so the next cycle retries
            // the same page instead of skipping it.
            DB::transaction(function () use ($page, $peerId): void {
                foreach ($page['changes'] as $change) {
                    $this->applier->apply(new SyncChange($change));
                }

                $this->advanceInboundCursor($peerId, (int) $page['cursor']);
            });

            $pulled += count($page['changes']);

            if ($page['cursor'] <= $cursor) {
                break;
            }

            $cursor = (int) $page['cursor'];
        } while ($page['has_more']);

        return [$pulled, $cursor];
    }

    private function inboundCursor(string $peerId): int
    {
        return $this->cursorValue($peerId, 'in');
    }

    private function outboundCursor(string $peerId): int
    {
        return $this->cursorValue($peerId, 'out');
    }

    private function cursorValue(string $peerId, string $direction): int
    {
        $value = SyncCursor::query()
            ->where('node_id', (string) config('sync.node_id'))
            ->where('target_node_id', $peerId)
            ->where('direction', $direction)
            ->value('last_applied_change_id');

        return (int) ($value ?? 0);
    }

    private function advanceInboundCursor(string $peerId, int $changeId): void
    {
        $this->writeCursor($peerId, 'in', $changeId);
    }

    private function advanceOutboundCursor(string $peerId, int $changeId): void
    {
        $this->writeCursor($peerId, 'out', $changeId);
    }

    private function writeCursor(string $peerId, string $direction, int $changeId): void
    {
        SyncCursor::updateOrCreate(
            [
                'node_id'        => (string) config('sync.node_id'),
                'target_node_id' => $peerId,
                'direction'      => $direction,
            ],
            [
                'last_applied_change_id' => $changeId,
                'last_synced_at'         => now(),
            ],
        );
    }
}
