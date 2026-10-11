<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use App\Sync\SyncApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Node-to-node change exchange.
 *
 * Reachable on the LAN and on the cloud, so every route authenticates the
 * calling node before reading or writing anything.
 */
class ChangeLogController extends Controller
{
    public function __construct(private readonly SyncApplier $applier)
    {
    }

    /**
     * Accept changes from a peer and apply them.
     *
     * The whole batch applies in one transaction. If any change fails, none
     * are committed and the caller gets an error, so the caller's outbound
     * cursor does not advance past work that was not actually stored.
     *
     * The receiver also advances its own inbound cursor for the sender, so a
     * later pull in the other direction does not re-fetch what was pushed.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'changes'               => ['required', 'array', 'max:1000'],
            'changes.*.change_id'   => ['required', 'integer', 'min:1'],
            'changes.*.table_name'  => ['required', 'string'],
            'changes.*.row_id'      => ['required', 'string'],
            'changes.*.operation'   => ['required', 'in:create,update,delete'],
            'changes.*.node_id'     => ['required', 'string'],
            'changes.*.hlc'         => ['required', 'string'],
            'changes.*.payload'     => ['nullable', 'array'],
        ]);

        $changes = $validated['changes'];
        $accepted = 0;

        DB::transaction(function () use ($changes, &$accepted): void {
            foreach ($changes as $change) {
                $this->applier->apply(new SyncChange($change));

                $accepted++;
            }

            $highest = (int) max(array_column($changes, 'change_id'));
            $sender = $this->callerNodeId();

            if ($sender !== null) {
                $this->advanceInboundCursor($sender, $highest);
            }
        });

        return response()->json(['accepted' => $accepted]);
    }

    /**
     * Return this node's changes past the caller's cursor.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'since' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = (int) ($validated['limit'] ?? config('sync.batch_size'));

        $changes = SyncChange::where('node_id', (string) config('sync.node_id'))
            ->where('id', '>', $validated['since'])
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $changes->count() > $limit;
        $page = $hasMore ? $changes->take($limit) : $changes;

        return response()->json([
            'changes'  => $page->map(fn (SyncChange $c) => [
                'table_name' => $c->table_name,
                'row_id'     => (string) $c->row_id,
                'operation'  => $c->operation,
                'node_id'    => $c->node_id,
                'hlc'        => $c->hlc,
                'payload'    => $c->payload,
            ])->values()->all(),
            'cursor'   => (int) ($page->last()?->id ?? $validated['since']),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * The authenticated peer that made this request.
     */
    private function callerNodeId(): ?string
    {
        $node = request()->attributes->get('sync_node');

        return $node?->node_id;
    }

    /**
     * How far this node has consumed the sender's log.
     *
     * Keyed by (this node, sender, direction) so a push and a pull advance the
     * same inbound position instead of racing over one row.
     */
    private function advanceInboundCursor(string $sender, int $changeId): void
    {
        $self = (string) config('sync.node_id');

        if ($self === '') {
            return;
        }

        SyncCursor::updateOrCreate(
            ['node_id' => $self, 'target_node_id' => $sender, 'direction' => 'in'],
            ['last_applied_change_id' => $changeId, 'last_synced_at' => now()],
        );
    }
}
